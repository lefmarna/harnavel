<?php

declare(strict_types=1);

namespace App\Services\GoogleAuth;

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use JsonException;

class GoogleAuthService implements GoogleAuthServiceContract
{
    private const MAX_CREATE_RETRIES = 5;

    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    public function findOrCreate(string $googleId, string $email, ?string $name): User
    {
        $name = $this->normalizeName($name, $email);

        // config() の戻り値は mixed で int 等の真値があり得る。bool 型引数へ渡すため
        // strict_types 下ではこのキャストが必須（非 bool 設定値のテストが変異を殺す）。
        $lowercaseUsernames = (bool) config('fortify.lowercase_usernames');
        $caseInsensitiveEmail = ! $lowercaseUsernames;

        if ($lowercaseUsernames) {
            $email = Str::lower($email);
        }

        // 終端条件 < を <= にしても、内側の枯渇判定（$attempt === MAX_CREATE_RETRIES - 1）が
        // 最終試行で必ず return / throw するため余分な反復には到達しない（SmallerToSmallerOrEqual のみ等価）。
        // カウンタの初期値・増減方向はリトライ予算（試行回数）を変える観測可能な変異なので ignore せずテストで殺す。
        for ($attempt = 0; $attempt < self::MAX_CREATE_RETRIES; $attempt++) { // @pest-mutate-ignore: SmallerToSmallerOrEqual
            try {
                return DB::transaction(fn (): User => $this->findOrCreateWithinTransaction(
                    googleId: $googleId,
                    email: $email,
                    name: $name,
                    caseInsensitiveEmail: $caseInsensitiveEmail,
                    lowercaseUsernames: $lowercaseUsernames,
                ));
            } catch (GoogleLinkConfirmationPendingException $e) {
                $this->sendLinkConfirmationEmail($e->user, $e->googleId, $e->email);

                throw $e;
            } catch (UniqueConstraintViolationException) {
                try {
                    $user = $this->recoverFromUniqueConstraintViolation($googleId, $email, $lowercaseUsernames);
                } catch (GoogleLinkConfirmationPendingException $e) {
                    $this->sendLinkConfirmationEmail($e->user, $e->googleId, $e->email);

                    throw $e;
                }

                if ($user !== null) {
                    return $user;
                }

                if ($attempt === self::MAX_CREATE_RETRIES - 1) {
                    Log::warning('Google OAuth: UniqueConstraintViolationException but user not found.', [
                        'google_id' => $googleId,
                    ]);

                    throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。');
                }
            }
        }

        // @codeCoverageIgnoreStart
        throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。');
        // @codeCoverageIgnoreEnd
    }

    public function confirmLink(string $userId, string $googleId, string $email): bool
    {
        try {
            return DB::transaction(function () use ($userId, $googleId, $email): bool {
                $user = $this->userRepository->find($userId, lock: true);

                if (! $user instanceof User) {
                    Log::warning('Google link confirmation failed because the user was not found.', [
                        'user_id' => $userId,
                    ]);

                    return false;
                }

                if (! $this->emailsMatch($user->email, $email)) {
                    Log::warning('Google link confirmation failed because the email did not match.', [
                        'user_id' => $userId,
                    ]);

                    return false;
                }

                if ($user->google_id === $googleId) {
                    return true;
                }

                if ($user->google_id !== null) {
                    Log::warning('Google link confirmation failed because a different Google account is already linked.', [
                        'user_id' => $userId,
                    ]);

                    return false;
                }

                $user->google_id = $googleId;

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()]);
                }

                $this->userRepository->save($user);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $linkedUser = $this->userRepository->findByGoogleId($googleId);

            return $linkedUser instanceof User && $this->userKey($linkedUser) === $userId;
        }
    }

    private function findOrCreateWithinTransaction(string $googleId, string $email, string $name, bool $caseInsensitiveEmail, bool $lowercaseUsernames): User
    {
        $candidates = $this->userRepository->findCandidates(
            $googleId,
            $email,
            lock: true,
        );
        $userByGoogleId = $candidates->firstWhere('google_id', $googleId);
        $userByEmail = $this->findCandidateByEmail($candidates, $email, $caseInsensitiveEmail);

        if ($userByGoogleId && $userByEmail && ! $userByGoogleId->is($userByEmail)) {
            throw new GoogleAuthException('このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
        }

        $user = $userByGoogleId ?? $userByEmail;

        if ($user !== null) {
            // google_id では一致せずメールだけで既存ユーザーに当たり、その既存ユーザーがまだ google_id
            // 未連携なら、本人確認のため確認メール待ちにする。$userByGoogleId === null のとき $user は
            // $userByEmail と同一なので $user の google_id を見れば足りる（冗長な null ガードを置かない）。
            if ($userByGoogleId === null && $user->google_id === null) {
                throw new GoogleLinkConfirmationPendingException($user, $googleId, $user->email);
            }

            return $this->syncGoogleAccount($user, $googleId, $lowercaseUsernames, $email);
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'google_id' => $googleId,
        ]);
        $user->forceFill(['email_verified_at' => now()]);
        $this->userRepository->save($user);

        return $user;
    }

    private function recoverFromUniqueConstraintViolation(string $googleId, string $email, bool $lowercaseUsernames): ?User
    {
        $userByGoogleId = $this->userRepository->findByGoogleId($googleId);
        $userByEmail = $this->userRepository->findByEmail($email);

        if ($userByGoogleId && $userByEmail && ! $userByGoogleId->is($userByEmail)) {
            throw new GoogleAuthException('このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
        }

        if ($userByGoogleId === null && $userByEmail !== null && $userByEmail->google_id === null) {
            throw new GoogleLinkConfirmationPendingException($userByEmail, $googleId, $userByEmail->email);
        }

        $user = $userByGoogleId ?? $userByEmail;

        if ($user === null) {
            return null;
        }

        return $this->syncGoogleAccount($user, $googleId, $lowercaseUsernames, $email);
    }

    private function syncGoogleAccount(User $user, string $googleId, bool $lowercaseUsernames, string $email): User
    {
        if ($user->google_id !== null && $user->google_id !== $googleId) {
            throw new GoogleAuthException('このメールアドレスは別のGoogleアカウントに連携済みです。');
        }

        // @codeCoverageIgnoreStart
        if ($user->google_id === null) {
            $user->google_id = $googleId;
        }
        // @codeCoverageIgnoreEnd

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()]);
        }

        if ($lowercaseUsernames && $user->email !== $email) {
            $user->email = $email;
        }

        if ($user->isDirty()) {
            $this->userRepository->save($user);
        }

        return $user;
    }

    private function emailsMatch(string $storedEmail, string $payloadEmail): bool
    {
        return Str::lower($storedEmail) === Str::lower($payloadEmail);
    }

    private function sendLinkConfirmationEmail(User $user, string $googleId, string $email): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'auth.google.link.verify',
            now()->addHour(),
            [
                'userId' => $this->userKey($user),
                'payload' => $this->encryptLinkPayload($googleId, $email),
            ],
        );

        Mail::to($user->email)->send(new LinkGoogleAccountMail($user, $signedUrl));
    }

    private function encryptLinkPayload(string $googleId, string $email): string
    {
        try {
            return Crypt::encryptString(json_encode([
                'google_id' => $googleId,
                'email' => $email,
            ], JSON_THROW_ON_ERROR));
            // @codeCoverageIgnoreStart
        } catch (JsonException $e) {
            throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。', previous: $e);
        }
        // @codeCoverageIgnoreEnd
    }

    private function normalizeName(?string $name, string $email): string
    {
        // preg_replace は正規表現エラー時のみ null を返すが、このパターンでは発生しないため
        // ?? のフォールバック値は実質到達不能（フォールバック値を変える変異は等価）。
        $trimmed = preg_replace('/^\s++|\s++$/u', '', (string) $name) ?? ''; // @pest-mutate-ignore: EmptyStringToNotEmpty

        return $trimmed !== '' ? $trimmed : $email;
    }

    private function userKey(User $user): string
    {
        $key = $user->getKey();

        if (is_int($key) || is_string($key)) {
            return (string) $key;
        }

        // @codeCoverageIgnoreStart
        throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。');
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  Collection<int, User>  $candidates
     */
    private function findCandidateByEmail(Collection $candidates, string $email, bool $caseInsensitiveEmail): ?User
    {
        if (! $caseInsensitiveEmail) {
            // lowercase_usernames=true では email が小文字に正規化済みのため厳密一致で照合する。
            // レガシーな大小混在メールの候補にはここでヒットしないが、続く INSERT が
            // utf8mb4_general_ci の一意制約に当たり、リカバリ（findByEmail は CI 照合）が
            // 同じ連携確認待ちへ収束する。早期 return の除去はこの契約前提の下では観測不能で、
            // kill するには collation 前提を破る fake が必要になる（実証済み）ため等価扱いとする。
            /** @var User|null */
            return $candidates->firstWhere('email', $email); // @pest-mutate-ignore: RemoveEarlyReturn
        }

        /** @var User|null */
        return $candidates->first(fn (User $user): bool => Str::lower($user->email) === Str::lower($email));
    }
}

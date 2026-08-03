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

        $lowercaseUsernames = (bool) config('fortify.lowercase_usernames');
        $caseInsensitiveEmail = ! $lowercaseUsernames;

        if ($lowercaseUsernames) {
            $email = Str::lower($email);
        }

        for ($attempt = 0; $attempt < self::MAX_CREATE_RETRIES; $attempt++) {
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
            }
        }

        Log::warning('Google OAuth: UniqueConstraintViolationException but user not found.', [
            'google_id' => $googleId,
        ]);

        throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。');
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
        $trimmed = preg_replace('/^\s++|\s++$/u', '', (string) $name) ?? '';

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
            // 大小混在の既存値はDBのCI一意制約を経てリカバリ経路で照合される。
            /** @var User|null */
            return $candidates->firstWhere('email', $email);
        }

        /** @var User|null */
        return $candidates->first(fn (User $user): bool => Str::lower($user->email) === Str::lower($email));
    }
}

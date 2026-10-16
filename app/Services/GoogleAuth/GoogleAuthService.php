<?php

declare(strict_types=1);

namespace App\Services\GoogleAuth;

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Mail\LinkGoogleAccountMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\Account\SecurityNotifier;
use App\Support\SignedPayloadLink;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GoogleAuthService implements GoogleAuthServiceContract
{
    private const MAX_CREATE_RETRIES = 5;

    public function __construct(
        private readonly UserRepositoryContract $userRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {}

    public function findOrCreate(string $googleId, string $email, ?string $name): User
    {
        $name = $this->normalizeName($name, $email);

        $lowercaseUsernames = (bool) config('fortify.lowercase_usernames');
        $caseInsensitiveEmail = ! $lowercaseUsernames;

        if ($lowercaseUsernames) {
            $email = Str::lower($email);
        }

        try {
            for ($attempt = 0; $attempt < self::MAX_CREATE_RETRIES; $attempt++) {
                try {
                    return DB::transaction(fn (): User => $this->findOrCreateWithinTransaction(
                        googleId: $googleId,
                        email: $email,
                        name: $name,
                        caseInsensitiveEmail: $caseInsensitiveEmail,
                        lowercaseUsernames: $lowercaseUsernames,
                    ));
                } catch (UniqueConstraintViolationException) {
                    $user = $this->recoverFromUniqueConstraintViolation($googleId, $email, $lowercaseUsernames);

                    if ($user !== null) {
                        return $user;
                    }
                }
            }
        } catch (GoogleLinkConfirmationPendingException $e) {
            $this->sendLinkConfirmationEmail($e->user, $e->googleId);

            throw $e;
        }

        Log::warning('Google OAuth: UniqueConstraintViolationException but user not found.', [
            'google_id' => $googleId,
        ]);

        throw new GoogleAuthException(__('auth.google.failed'));
    }

    public function confirmLink(string $userId, string $googleId, string $email, string $sessionGeneration): bool
    {
        $linkedUser = null;

        try {
            $confirmed = DB::transaction(function () use ($userId, $googleId, $email, $sessionGeneration, &$linkedUser): bool {
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

                // 送った後にセッションを失効させていれば、解除した連携を古いリンクで戻させない（TADR-0016）。
                if ((string) $user->session_generation !== $sessionGeneration) {
                    Log::warning('Google link confirmation failed because the sessions were revoked after the link was sent.', [
                        'user_id' => $userId,
                    ]);

                    return false;
                }

                $revokesExistingCredentials = $user->email_verified_at === null;

                if ($revokesExistingCredentials) {
                    $user->reclaimForEmailOwner();
                    $user->revokeSessions();
                }

                $user->google_id = $googleId;
                $this->userRepository->save($user);

                Log::info('Google account was linked.', [
                    'user_id' => $user->getKey(),
                    'existing_credentials_revoked' => $revokesExistingCredentials,
                ]);

                $linkedUser = $user;

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $existingUser = $this->userRepository->findByGoogleId($googleId);

            return $existingUser instanceof User && $this->userKey($existingUser) === $userId;
        }

        if ($linkedUser instanceof User) {
            $this->securityNotifier->notify($linkedUser, 'google_linked');
        }

        return $confirmed;
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

        $user = $userByGoogleId ?? $userByEmail;

        if ($user !== null) {
            if ($userByGoogleId === null && $user->google_id === null) {
                throw new GoogleLinkConfirmationPendingException($user, $googleId);
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

        if ($userByGoogleId === null && $userByEmail !== null && $userByEmail->google_id === null) {
            throw new GoogleLinkConfirmationPendingException($userByEmail, $googleId);
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
            throw new GoogleAuthException(__('auth.google.email_taken'));
        }

        // Google ID の一致はログインの根拠になるが、メールの所有を示すのは同じアドレスのときだけ。
        // 違うアドレスを確認済みにしたり、本人が設定画面で変えたアドレスを書き戻したりしない。
        if ($this->emailsMatch($user->email, $email)) {
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()]);
            }

            if ($lowercaseUsernames && $user->email !== $email) {
                $user->email = $email;
            }
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

    private function sendLinkConfirmationEmail(User $user, string $googleId): void
    {
        $signedUrl = SignedPayloadLink::make('auth.google.link.verify', now()->addHour(), $this->userKey($user), [
            'google_id' => $googleId,
            'email' => $user->email,
            'session_generation' => (string) $user->session_generation,
        ]);

        Mail::to($user->email)->send(new LinkGoogleAccountMail($user, $signedUrl));
    }

    private function normalizeName(?string $name, string $email): string
    {
        $trimmed = preg_replace('/^\s++|\s++$/u', '', (string) $name) ?? '';

        return $trimmed !== '' ? $trimmed : $email;
    }

    private function userKey(User $user): string
    {
        return (string) $user->id;
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

<?php

declare(strict_types=1);

namespace App\Services\GoogleAuth;

use App\Exceptions\GoogleAuthException;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthService implements GoogleAuthServiceContract
{
    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    public function findOrCreate(string $googleId, string $email, string $name): User
    {
        $lowercaseUsernames = (bool) config('fortify.lowercase_usernames');
        $caseInsensitiveEmail = ! $lowercaseUsernames;

        if ($lowercaseUsernames) {
            $email = Str::lower($email);
        }

        try {
            return DB::transaction(function () use ($googleId, $email, $name, $lowercaseUsernames, $caseInsensitiveEmail): User {
                $candidates = $this->userRepository->findCandidates(
                    $googleId,
                    $email,
                    lock: true,
                );
                $userByGoogleId = $candidates->firstWhere('google_id', $googleId);
                $userByEmail = $this->findCandidateByEmail($candidates, $email, $caseInsensitiveEmail);

                // google_id 一致ユーザーとメール一致ユーザーが異なるレコードの場合は衝突エラー
                if ($userByGoogleId && $userByEmail && ! $userByGoogleId->is($userByEmail)) {
                    throw new GoogleAuthException('このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
                }

                $user = $userByGoogleId ?? $userByEmail;

                if ($user) {
                    // 別の Google アカウントがすでに紐付いている場合はエラー
                    if ($user->google_id !== null && $user->google_id !== $googleId) {
                        throw new GoogleAuthException('このメールアドレスは別のGoogleアカウントに連携済みです。');
                    }

                    if ($user->google_id === null) {
                        $user->google_id = $googleId;
                    }

                    if ($user->email_verified_at === null) {
                        $user->forceFill(['email_verified_at' => now()]);
                    }

                    if ($lowercaseUsernames && $user->email !== $email) {
                        $user->email = $email;
                    }

                    if ($user->isDirty()) {
                        $this->userRepository->save($user);
                    }
                } else {
                    $user = new User([
                        'name' => $name,
                        'email' => $email,
                        'google_id' => $googleId,
                    ]);
                    $user->forceFill(['email_verified_at' => now()]);
                    $this->userRepository->save($user);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // 同時リクエストによる競合: 先行リクエストが作成したユーザーを再取得
            $userByGoogleId = $this->userRepository->findByGoogleId($googleId);
            $userByEmail = $this->userRepository->findByEmail($email);

            // フォールバック時も衝突判定を再実行する
            // （別トランザクションが google_id 側と email 側それぞれを別々に作成した場合）
            if ($userByGoogleId && $userByEmail && ! $userByGoogleId->is($userByEmail)) {
                throw new GoogleAuthException('このGoogleアカウントに紐づく既存ユーザーと、同じメールアドレスの別ユーザーが存在します。');
            }

            $user = $userByGoogleId ?? $userByEmail;

            if ($user === null) {
                Log::warning('Google OAuth: UniqueConstraintViolationException but user not found.', [
                    'google_id' => $googleId,
                ]);

                throw new GoogleAuthException('Google認証に失敗しました。もう一度お試しください。');
            }

            if ($user->google_id !== null && $user->google_id !== $googleId) {
                throw new GoogleAuthException('このメールアドレスは別のGoogleアカウントに連携済みです。');
            }

            if ($user->google_id === null) {
                $user->google_id = $googleId;
            }

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
    }

    /**
     * @param  Collection<int, User>  $candidates
     */
    private function findCandidateByEmail(Collection $candidates, string $email, bool $caseInsensitiveEmail): ?User
    {
        if (! $caseInsensitiveEmail) {
            /** @var User|null */
            return $candidates->firstWhere('email', $email);
        }

        /** @var User|null */
        return $candidates->first(fn (User $user): bool => Str::lower($user->email) === Str::lower($email));
    }
}

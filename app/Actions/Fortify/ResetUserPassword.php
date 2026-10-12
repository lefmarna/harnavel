<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\AccountLoginThrottle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        /** @var array{password: string} $validated */
        $validated = Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        /** @var int|string $key */
        $key = $user->getKey();

        $tokenEmail = $user->email;

        DB::transaction(function () use ($user, $key, $tokenEmail, $validated, $input): void {
            // Fortify はロックを取る前に読み込んだユーザーを渡すため、確認状態はロックした行で判定する。
            $lockedUser = $this->userRepository->find($key, lock: true)
                ?? throw (new ModelNotFoundException)->setModel(User::class, [$key]);

            if ($lockedUser->email !== $tokenEmail
                || ! Password::broker(config()->string('fortify.passwords'))->tokenExists($lockedUser, $input['token'] ?? '')) {
                throw ValidationException::withMessages(['email' => [__('passwords.token')]]);
            }

            // 再設定リンクを受け取れたことは、メールの所有を示す（TADR-0016）。
            $revokesExistingCredentials = $lockedUser->email_verified_at === null;

            if ($revokesExistingCredentials) {
                $lockedUser->reclaimForEmailOwner();
            }

            $lockedUser->forceFill([
                'password' => Hash::make($validated['password']),
            ]);
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            // Fortify はこの後に渡したユーザーを保存し、そのメールでトークンを消す。
            // ロック中に保存した値を写し、古い値の書き戻しと、照合時と違うアドレスでの消去を防ぐ。
            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);

            if ($revokesExistingCredentials) {
                Log::info('Unverified account credentials were revoked by password reset.', [
                    'user_id' => $lockedUser->getKey(),
                ]);
            }
        });

        AccountLoginThrottle::reset($tokenEmail);
    }
}

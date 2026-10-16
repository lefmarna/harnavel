<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\RememberCookie;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\DeletePasskey as BaseDeletePasskey;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Passkey;

/**
 * 削除したパスキーで先にログインした第三者のセッションを残さないため、他の端末のセッションも失効させる（TADR-0016）。
 */
class DeletePasskey extends BaseDeletePasskey
{
    public function __construct(
        private readonly UserRepositoryContract $userRepository,
        private readonly Session $session,
    ) {}

    public function __invoke(Authenticatable $user, Passkey $passkey): void
    {
        /** @var User $user 認証するのは users テーブルだけ。 */
        /** @var int|string $key */
        $key = $user->getKey();
        $rememberToken = $user->getRememberToken();

        $lockedUser = DB::transaction(function () use ($user, $key, $passkey): User {
            $lockedUser = $this->userRepository->find($key, lock: true);

            // 途中で失効させていれば、失効した第三者のセッションに新しい世代を記録させない。
            if ($lockedUser === null || $lockedUser->session_generation !== $user->session_generation) {
                abort(409);
            }

            $passkey->delete();
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            return $lockedUser;
        });

        PasskeyDeleted::dispatch($user, $passkey);

        $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        $this->session->migrate(destroy: true);
        $lockedUser->recordSessionGeneration($this->session);
        RememberCookie::reissue($lockedUser, $rememberToken);
    }
}

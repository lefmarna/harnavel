<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\Account\SessionRevocation;
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
        private readonly SessionRevocation $sessionRevocation,
        private readonly Session $session,
    ) {}

    public function __invoke(Authenticatable $user, Passkey $passkey): void
    {
        /** @var User $user 認証するのは users テーブルだけ。 */
        $rememberToken = $user->getRememberToken();

        $lockedUser = DB::transaction(function () use ($user, $passkey): User {
            $lockedUser = $this->sessionRevocation->lockUnrevoked($user);

            $passkey->delete();
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            return $lockedUser;
        });

        PasskeyDeleted::dispatch($user, $passkey);

        $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        $this->sessionRevocation->keepCurrentSession($this->session, $user, $rememberToken);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Models\User;
use App\Services\Account\SessionRevocation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Laravel\Passkeys\Actions\DeletePasskey as BaseDeletePasskey;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Passkey;

/**
 * 削除したパスキーで先にログインした第三者のセッションを残さないため、他の端末のセッションも失効させる（TADR-0016）。
 */
class DeletePasskey extends BaseDeletePasskey
{
    public function __construct(
        private readonly SessionRevocation $sessionRevocation,
        private readonly Session $session,
    ) {}

    public function __invoke(Authenticatable $user, Passkey $passkey): void
    {
        /** @var User $user */
        $this->sessionRevocation->revokeOtherSessions(
            $this->session,
            $user,
            mutate: static function () use ($passkey): void {
                $passkey->delete();
            },
            // セッション更新が失敗しても、確定済みの削除を監査ログへ残すためコミット直後に発行する。
            afterCommit: static function () use ($user, $passkey): void {
                PasskeyDeleted::dispatch($user, $passkey);
            },
        );
    }
}

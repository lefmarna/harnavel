<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Models\User;
use App\Services\Account\SessionRevocation;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Webauthn\CredentialRecord;

/**
 * 失効より前に始まった登録が、失効の後にパスキーを保存しないようにする（TADR-0016）。
 * 保存してから消すと、その間に別のリクエストがそのパスキーでログインできるので、保存の前に止める。
 */
class StorePasskey extends BaseStorePasskey
{
    public function __construct(private readonly SessionRevocation $sessionRevocation) {}

    public function createPasskey(PasskeyUser $user, string $name, CredentialRecord $source): Passkey
    {
        return DB::transaction(function () use ($user, $name, $source): Passkey {
            /** @var User $user 認証するのは users テーブルだけ。 */
            $this->sessionRevocation->lockUnrevoked($user);

            return parent::createPasskey($user, $name, $source);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Passkeys;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Webauthn\CredentialRecord;

/**
 * 失効より前に始まった登録が、失効の後にパスキーを保存しないようにする（TADR-0016）。
 * 保存してから消すと、その間に別のリクエストがそのパスキーでログインできるので、保存の前に止める。
 * 取り消しや再設定と同じ行ロックの下で、登録を始めたときの世代と照合する。
 */
class StorePasskey extends BaseStorePasskey
{
    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    public function createPasskey(PasskeyUser $user, string $name, CredentialRecord $source): Passkey
    {
        return DB::transaction(function () use ($user, $name, $source): Passkey {
            /** @var User $user 認証するのは users テーブルだけ。 */
            /** @var int|string $key Eloquent::getKey() は mixed を返す。 */
            $key = $user->getKey();

            $lockedUser = $this->userRepository->find($key, lock: true);

            if ($lockedUser === null || $lockedUser->session_generation !== $user->session_generation) {
                abort(409);
            }

            return parent::createPasskey($user, $name, $source);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

/**
 * Larastan は Blueprint::rememberToken() の nullable を推論できない。推論できるようになったら消す。
 *
 * @property string|null $remember_token
 */
#[Fillable(['name', 'email', 'password', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * iOS Safari が生バイナリを破損するため、64バイトの ASCII hex を使う。
     * https://github.com/laravel/passkeys-server/pull/42 が正式版へ入ったら削除する（TADR-0013）。
     */
    public function getPasskeyUserHandle(): string
    {
        /** @var int|string $key Eloquent::getKey() は mixed を返す。 */
        $key = $this->getKey();

        return hash_hmac(
            'sha256',
            $this->getTable().'|'.$key,
            Config::string('passkeys.user_handle_secret'),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

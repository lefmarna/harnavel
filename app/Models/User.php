<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\GoogleOAuth;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * Google での再ログインを本人確認として使えるか。
     */
    public function canConfirmWithGoogle(): bool
    {
        return $this->google_id !== null && GoogleOAuth::isConfigured();
    }

    /**
     * メール未確認のアカウントは、メールの持ち主でない第三者が先に作った可能性がある。
     * 持ち主がメールの所有を示した時点で、その第三者が用意した資格情報を消して確認済みにする（TADR-0016）。
     * 持ち主の資格情報とセッションの失効は呼び出し側で扱い、保存も呼び出し側で行う。
     */
    public function reclaimForEmailOwner(): void
    {
        $this->forceFill([
            'email_verified_at' => now(),
            'password' => null,
            'google_id' => null,
        ]);
        $this->passkeys()->delete();
    }

    /**
     * 窃取されたセッションとログイン保持 Cookie を使えなくする。remember_token は呼び出し側で保存する。
     * 端末ごとのセッションを列挙できるのは DB セッションドライバーだけなので、それを前提にする。
     */
    public function revokeSessions(?string $exceptSessionId = null): void
    {
        $this->setRememberToken(Str::random(60));

        /** @var string|null $connection */
        $connection = Config::get('session.connection');

        $sessions = DB::connection($connection)
            ->table(Config::string('session.table'))
            ->where('user_id', $this->getKey());

        if ($exceptSessionId !== null) {
            $sessions->where('id', '!=', $exceptSessionId);
        }

        $sessions->delete();
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

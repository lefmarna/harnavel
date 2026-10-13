<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\GoogleOAuth;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
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

    public const SESSION_GENERATION_KEY = 'session_generation';

    /**
     * DB の既定値と揃え、作成しただけで読み直していないモデルでも世代を比べられるようにする。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'session_generation' => 0,
    ];

    /**
     * Laravel はログイン保持の値が空のときだけ作って保存する。失効の前に読み込んだユーザーでの
     * ログインに、失効でローテーションした値を上書きさせないよう、作成時から値を持たせる（TADR-0016）。
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->getRememberToken())) {
                $user->setRememberToken(Str::random(60));
            }
        });
    }

    /**
     * iOS Safari が生バイナリを破損するため、64バイトの ASCII hex を使う。
     * https://github.com/laravel/passkeys-server/pull/42 が正式版へ入ったら削除する（TADR-0013）。
     */
    public function getPasskeyUserHandle(): string
    {
        /** @var int|string $key */
        $key = $this->getKey();

        return hash_hmac(
            'sha256',
            $this->getTable().'|'.$key,
            Config::string('passkeys.user_handle_secret'),
        );
    }

    public function markEmailAsVerified(): bool
    {
        $verifiedAt = now()->toImmutable();

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('email', $this->email)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => $verifiedAt]);

        if ($updated === 0) {
            return false;
        }

        $this->email_verified_at = $verifiedAt;
        $this->syncOriginalAttribute('email_verified_at');

        return true;
    }

    /**
     * パスワードがあればそちらで確認させ、Google のログイン状態だけでは通さない（TADR-0016）。
     */
    public function canConfirmWithGoogle(): bool
    {
        return $this->password === null && $this->google_id !== null && GoogleOAuth::isConfigured();
    }

    /**
     * メールの持ち主が所有を示したとき、先に登録した第三者の資格情報を消す（TADR-0016）。
     * セッションの失効と保存は呼び出し側で行う。
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
     * 保存は呼び出し側で行う（TADR-0016）。前に読んだ値から進めると並行する失効で進んだ世代を
     * 巻き戻すため、ロックして読み直した行で呼ぶ。今のセッションを残すときは、保存の後に
     * recordSessionGeneration() を呼ぶ。
     */
    public function revokeSessions(): void
    {
        $this->setRememberToken(Str::random(60));
        $this->session_generation++;
    }

    public function recordSessionGeneration(Session $session): void
    {
        $session->put(self::SESSION_GENERATION_KEY, $this->session_generation);
    }

    /**
     * 世代を記録する前からのセッションは、まだ一度も失効させていない世代 0 として扱う。
     */
    public function isCurrentSessionGeneration(Session $session): bool
    {
        return $session->get(self::SESSION_GENERATION_KEY, 0) === $this->session_generation;
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
            'session_generation' => 'integer',
        ];
    }
}

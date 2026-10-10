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

    /**
     * ログインした時点の世代を記録するセッションのキー。
     */
    public const SESSION_GENERATION_KEY = 'session_generation';

    /**
     * DB の既定値と揃え、作成しただけで読み直していないモデルでも世代を比べられるようにする。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'session_generation' => 0,
        'email_changed_at' => null,
    ];

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
     * パスワードがあればそちらで確認させ、Google のログイン状態だけでは通さない（TADR-0016）。
     */
    public function canConfirmWithGoogle(): bool
    {
        return $this->password === null && $this->google_id !== null && GoogleOAuth::isConfigured();
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
     * 窃取されたセッションとログイン保持 Cookie を使えなくする。保存は呼び出し側で行う。
     * セッションの行は消さず、世代を進めて RejectRevokedSessions に弾かせる。行を消すだけでは、
     * 失効と同時に照合を終えたログインが、失効の後に新しい行を作って残る（TADR-0016）。
     * 前に読んだ値から進めると並行する失効で進んだ世代を巻き戻すため、ロックして読み直した行で呼ぶ。
     * 今のセッションを残すときは、保存の後に recordSessionGeneration() で新しい世代を記録する。
     */
    public function revokeSessions(): void
    {
        $this->setRememberToken(Str::random(60));
        $this->session_generation++;
    }

    /**
     * このユーザーの今の世代を、ログインしたセッションへ記録する。
     */
    public function recordSessionGeneration(Session $session): void
    {
        $session->put(self::SESSION_GENERATION_KEY, $this->session_generation);
    }

    /**
     * セッションに記録した世代が、このユーザーの今の世代と同じか。
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
            'email_changed_at' => 'datetime',
        ];
    }
}

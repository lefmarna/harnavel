<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * IP をまたいだ同じアカウントへのログイン失敗の上限。第三者に上限まで失敗させられても、
 * パスワード再設定で本人確認できた利用者はすぐにログインできるよう、キーの世代を進めて数え直す。
 */
final class AccountLoginThrottle
{
    public static function key(string $username): string
    {
        $username = self::normalize($username);

        $generation = Cache::get(self::generationKey($username));

        return 'account|'.$username.'|'.(is_string($generation) ? $generation : '');
    }

    public static function reset(string $username): void
    {
        Cache::forever(self::generationKey(self::normalize($username)), Str::random(16));
    }

    /**
     * メールアドレスをそのまま使うと DB キャッシュのキー列長を超えうるため、固定長に縮める。
     */
    private static function generationKey(string $username): string
    {
        return 'login-throttle-generation|'.hash('sha256', $username);
    }

    private static function normalize(string $username): string
    {
        return Str::transliterate(Str::lower($username));
    }
}

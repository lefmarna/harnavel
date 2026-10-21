<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class EmailAddress
{
    /**
     * 保存する値を、Fortify がログイン時に入力をそろえる規則に合わせる。
     */
    public static function normalize(string $email): string
    {
        return config('fortify.lowercase_usernames') ? Str::lower($email) : $email;
    }

    public static function same(string $email, string $other): bool
    {
        return Str::lower($email) === Str::lower($other);
    }
}

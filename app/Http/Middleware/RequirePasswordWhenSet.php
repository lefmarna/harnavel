<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * Google だけで登録したユーザーは password が null で、パスキーも持たないうちは
 * Fortify のパスワード確認もパスキー確認も通過できない。素の RequirePassword では
 * そのユーザーが最初の資格情報を登録する画面そのものに到達できなくなる。
 * パスキーを1つでも持てば確認できるので、省略の対象から外す。
 */
final class RequirePasswordWhenSet extends RequirePassword
{
    /**
     * 親の宣言どおり int|null で受け、そのまま渡す。Laravel の handle() は string|int|null だが、
     * 広げてキャストで詰めると数値文字列の比較が実行時等価になり、殺せないミュータントが残る。
     *
     * @param  Request  $request
     * @param  int|null  $passwordTimeoutSeconds
     */
    protected function shouldConfirmPassword($request, $passwordTimeoutSeconds = null): bool
    {
        $user = $request->user();

        if ($user instanceof User && $user->password === null && $user->passkeys()->doesntExist()) {
            return false;
        }

        return parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }
}

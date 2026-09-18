<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * Confirm the credential only for accounts that hold one.
 *
 * Google だけで登録したユーザーは password が null で、パスキーも持たないうちは
 * Fortify のパスワード確認もパスキー確認も通過できない。素の RequirePassword では
 * そのユーザーが最初の資格情報を登録する画面そのものに到達できなくなる。
 * パスキーを1つでも持てば確認できるので、省略の対象から外す。
 */
final class RequirePasswordWhenSet extends RequirePassword
{
    /**
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

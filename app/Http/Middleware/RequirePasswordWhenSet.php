<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * 資格情報を1つも持たないユーザーは、素の RequirePassword では最初の資格情報を登録する画面に
 * 到達できないので確認を省く。Google ログインの設定が欠けていても、Google 連携済みなら省かない（TADR-0013）。
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

        if ($user instanceof User && $user->password === null && $user->google_id === null && $user->passkeys()->doesntExist()) {
            return false;
        }

        return parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }
}

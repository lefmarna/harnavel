<?php

declare(strict_types=1);

namespace App\Services\GoogleAuth;

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Models\User;
use Illuminate\Database\QueryException;

interface GoogleAuthServiceContract
{
    /**
     * Google OAuth の情報をもとにユーザーを取得または作成する。
     *
     * @throws GoogleAuthException ビジネスルール違反（アカウント衝突・別 Google アカウント連携済み等）
     * @throws GoogleLinkConfirmationPendingException 既存メールユーザーの本人確認メール送信が必要な場合
     * @throws QueryException DB 例外（接続エラー・保存失敗など）
     */
    public function findOrCreate(string $googleId, string $email, ?string $name): User;

    /**
     * 確認メールの署名付きリンクをもとに既存ユーザーへ Google アカウントを連携する。
     */
    public function confirmLink(string $userId, string $googleId, string $email): bool;
}

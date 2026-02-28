<?php

declare(strict_types=1);

namespace App\Services\GoogleAuth;

use App\Models\User;

interface GoogleAuthServiceContract
{
    /**
     * Google OAuth の情報をもとにユーザーを取得または作成する。
     *
     * @throws \RuntimeException ビジネスルール違反（アカウント衝突・別 Google アカウント連携済み等）
     * @throws \Illuminate\Database\QueryException DB 例外（接続エラー・競合後の再取得失敗等）
     */
    public function findOrCreate(string $googleId, string $email, string $name): User;
}

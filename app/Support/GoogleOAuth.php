<?php

declare(strict_types=1);

namespace App\Support;

class GoogleOAuth
{
    /**
     * Google OAuth の設定（client_id / client_secret / redirect）が全て揃っているか確認する。
     *
     * GoogleAuthController と FortifyServiceProvider の両方から参照する共通ロジック。
     * 設定条件を変更する場合はこのメソッドだけ修正すれば両方に反映される。
     */
    public static function isConfigured(): bool
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirect = config('services.google.redirect');

        return is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && $clientSecret !== ''
            && is_string($redirect) && $redirect !== '';
    }
}

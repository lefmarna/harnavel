<?php

declare(strict_types=1);

namespace App\Support;

class GoogleOAuth
{
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

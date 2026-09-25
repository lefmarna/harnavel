<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', env('GEMINI_MODEL_NAME', 'gemini-3.5-flash-lite')),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'analytics_tracking_id' => env('GA_TRACKING_ID'),
        'adsense_publisher_id' => env('ADSENSE_PUBLISHER_ID'),

        // Socialite はこの配列を OAuth 用 HTTP クライアントへ渡す。未設定だと総 timeout は 0（無制限）で、
        // max_execution_time は I/O 待ちを数えない。追従を許すと timeout が転送ごとに再適用される。
        'guzzle' => [
            'connect_timeout' => 10,
            'timeout' => 15,
            'allow_redirects' => false,
        ],
    ],

];

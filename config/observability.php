<?php

return [
    'discord' => [
        'webhook_url' => env('ERROR_NOTIFIER_DISCORD_WEBHOOK_URL'),
        'timeout' => (float) env('ERROR_NOTIFIER_DISCORD_TIMEOUT', 2.0),
        'connect_timeout' => (float) env('ERROR_NOTIFIER_DISCORD_CONNECT_TIMEOUT', 1.0),
        'deduplication_seconds' => (int) env('ERROR_NOTIFIER_DEDUPLICATION_SECONDS', 300),
    ],
];

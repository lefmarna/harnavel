<?php

declare(strict_types=1);

return [
    'discord' => [
        'webhook_url' => env('ERROR_NOTIFIER_DISCORD_WEBHOOK_URL'),
        'timeout' => 2.0,
        'connect_timeout' => 1.0,
        'deduplication_seconds' => 300,
    ],
];

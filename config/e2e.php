<?php

declare(strict_types=1);

return [
    // 標準の Docker 開発環境は APP_ENV=local なので、環境名だけを条件にできない。
    // APP_ENV が読めない場合は production 扱いで閉じる。
    'helper_routes' => env('APP_ENV', 'production') !== 'production'
        && filter_var(env('E2E_HELPER_ROUTES', false), FILTER_VALIDATE_BOOL),
];

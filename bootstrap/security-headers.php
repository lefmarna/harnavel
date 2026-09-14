<?php

declare(strict_types=1);

// Composer や Laravel の起動前にも応答するエントリーポイントと共有する。
return [
    'Content-Security-Policy' => "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'",
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
];

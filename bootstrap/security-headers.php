<?php

declare(strict_types=1);

// Composer の autoload 前に応答する public/index.php と共有するため、素の配列で置く。
return [
    'Content-Security-Policy' => "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'",
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
];

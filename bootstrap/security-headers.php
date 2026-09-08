<?php

declare(strict_types=1);

// Shared with the entry point, which must work before Composer or Laravel boots.
return [
    'Content-Security-Policy' => "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'",
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
];

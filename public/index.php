<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// .htaccess とは別に秘密を照合し、自ゾーンを経た要求でだけ Cloudflare が付けた接続元を使う。照合した秘密は、
// ログや通知に渡さない（TADR-0019）。
$cloudflareSecret = $_SERVER['HTTP_X_CLOUDFLARE_PROXY_SECRET'] ?? null;
$cloudflareClient = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;

if (is_string($cloudflareSecret) && hash_equals('{CLOUDFLARE_PROXY_SECRET_SHA1}', sha1($cloudflareSecret))
    && filter_var($cloudflareClient, FILTER_VALIDATE_IP) !== false) {
    $_SERVER['REMOTE_ADDR'] = $cloudflareClient;
}

unset($cloudflareSecret, $cloudflareClient, $_SERVER['HTTP_X_CLOUDFLARE_PROXY_SECRET'], $_ENV['HTTP_X_CLOUDFLARE_PROXY_SECRET']);

/** @var array<string, string> $securityHeaders */
$securityHeaders = require __DIR__.'/../bootstrap/security-headers.php';

// Laravel 起動前の応答にも不足するヘッダーだけを補う。
// PHP のヘッダーコールバックは 1 枠なので、追加の連携でもこの処理を維持する。
header_register_callback(static function () use ($securityHeaders): void {
    $present = array_map(
        static fn (string $header): string => strtolower(explode(':', $header, 2)[0]),
        headers_list(),
    );

    foreach ($securityHeaders as $name => $value) {
        if (! in_array(strtolower($name), $present, true)) {
            header($name.': '.$value);
        }
    }
});

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());

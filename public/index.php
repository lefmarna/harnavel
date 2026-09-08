<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/** @var array<string, string> $securityHeaders */
$securityHeaders = require __DIR__.'/../bootstrap/security-headers.php';

// Keep the fallback available even if maintenance or startup exits before Laravel.
// Add only missing headers at send time so application policies are never duplicated.
// PHP has one header callback slot; integrations must preserve this callback.
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

<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// リリース同一性。delivery ワークフローが配布物側のこのリテラルだけをデプロイ試行固有IDへ
// 書き換える（リポジトリ側は常に空のまま）。全 HTTP リクエストが必ず実行する中央成果物へ
// 焼き込むため、Web 側 OPcache がこのファイルの旧エントリを返している間は旧リリースのIDしか
// 返せず、検証は偽陽性ではなく fail closed に倒れる。詳細は TADR-0006。
define('LARAVEL_DEPLOYMENT_RELEASE_ID', '');

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

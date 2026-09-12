<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

/**
 * 本物のエントリポイントをそのままHTTP SAPIで動かす。メンテナンスファイルと
 * 子プロセスの書き込みはこのフィクスチャ内に閉じ、共有アプリ側へ出さない。
 *
 * @param  Closure(string): void  $prepare
 * @param  array<string, string>  $headers
 * @param  list<string>  $ini
 */
function securityEntryPointResponse(Closure $prepare, string $path = '/', array $headers = [], array $ini = []): ResponseInterface
{
    $directory = sys_get_temp_dir().'/security-headers-'.bin2hex(random_bytes(8));
    $server = null;

    try {
        foreach (['public', 'bootstrap/cache', 'vendor', 'storage/framework/views', 'storage/logs'] as $subdirectory) {
            File::ensureDirectoryExists($directory.'/'.$subdirectory);
        }

        File::copy(base_path('public/index.php'), $directory.'/public/index.php');
        File::copy(base_path('bootstrap/security-headers.php'), $directory.'/bootstrap/security-headers.php');
        $prepare($directory);

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException("Cannot select a test port: {$errorMessage} ({$errorCode})");
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($address === false) {
            throw new RuntimeException('Cannot read the test server address.');
        }

        $command = [PHP_BINARY, '-d', 'display_errors=0'];

        foreach ($ini as $directive) {
            array_push($command, '-d', $directive);
        }

        $server = new Process([
            ...$command, '-S', $address,
            '-t', $directory.'/public', $directory.'/public/index.php',
        ], $directory, [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'VIEW_COMPILED_PATH' => $directory.'/storage/framework/views',
            'LOG_CHANNEL' => 'stderr',
            'PHP_CLI_SERVER_WORKERS' => false,
        ], timeout: 10);

        $server->start();
        $output = '';
        $started = $server->waitUntil(function (string $type, string $buffer) use (&$output, $address): bool {
            $output .= $buffer;

            return str_contains($output, "(http://{$address}) started");
        });

        // 上で選んだポートは候補にすぎない。別プロセスの応答を受け取らないこと。
        if (! $started || ! $server->isRunning()) {
            throw new RuntimeException('Test server did not start: '.$output);
        }

        $response = (new Client)->get('http://'.$address.$path, [
            'headers' => $headers,
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 5,
            'proxy' => '',
        ]);

        if (! $server->isRunning()) {
            throw new RuntimeException('Test server exited: '.$server->getErrorOutput());
        }

        return $response;
    } finally {
        $server?->stop();
        File::deleteDirectory($directory);
    }
}

function assertSecurityEntryPointBaseline(ResponseInterface $response): void
{
    // 契約値は独立したリテラルで持つ。配列での比較はヘッダーフィールドの重複も弾く。
    expect($response->getHeader('Content-Security-Policy'))
        ->toBe(["frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'"]);
    expect($response->getHeader('X-Content-Type-Options'))->toBe(['nosniff']);
    expect($response->getHeader('Referrer-Policy'))->toBe(['strict-origin-when-cross-origin']);
    expect($response->getHeader('X-Frame-Options'))->toBe(['DENY']);
}

/** @param array<string, string> $policy */
function bootSecurityEntryPoint(string $directory, array $policy = []): void
{
    File::put($directory.'/vendor/autoload.php', '<?php require '.var_export(base_path('vendor/autoload.php'), true).';');
    File::put($directory.'/bootstrap/app.php', sprintf(<<<'PHP'
        <?php
        $app = require %s;
        $app->useStoragePath(%s);
        $app->useBootstrapPath(%s);
        $app->booted(function () {
            Illuminate\Support\Facades\Route::get('/_testing/entry-point', fn () => response()->json(
                ['entry_point' => true], 200, %s,
            ));
        });
        return $app;
        PHP,
        var_export(base_path('bootstrap/app.php'), true),
        var_export($directory.'/storage', true),
        var_export($directory.'/bootstrap', true),
        var_export($policy, true),
    ));
}

it('事前描画メンテナンスの503にも基本のヘッダーを一つずつ返す', function () {
    $response = securityEntryPointResponse(function (string $directory): void {
        File::copy(
            base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub'),
            $directory.'/storage/framework/maintenance.php',
        );
        File::put($directory.'/storage/framework/down', json_encode([
            'template' => '<h1>Maintenance fixture</h1>',
            'status' => 503,
            'retry' => 60,
        ], JSON_THROW_ON_ERROR));
    });

    expect($response->getStatusCode())->toBe(503);
    expect((string) $response->getBody())->toBe('<h1>Maintenance fixture</h1>');
    expect($response->getHeader('Retry-After'))->toBe(['60']);
    assertSecurityEntryPointBaseline($response);
});

it('初期化に失敗した500にも基本のヘッダーを一つずつ返す', function (string $failureFile) {
    $response = securityEntryPointResponse(function (string $directory) use ($failureFile): void {
        File::put($directory.'/vendor/autoload.php', '<?php require '.var_export(base_path('vendor/autoload.php'), true).';');
        File::put($directory.'/'.$failureFile, '<?php throw new RuntimeException("Initialization fixture failure");');
    });

    expect($response->getStatusCode())->toBe(500);
    assertSecurityEntryPointBaseline($response);
})->with(['vendor/autoload.php', 'bootstrap/app.php']);

it('Laravelの通常応答への引き継ぎで基本のヘッダーを重複させない', function () {
    $response = securityEntryPointResponse(
        fn (string $directory) => bootSecurityEntryPoint($directory),
        '/_testing/entry-point',
    );

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getBody())->toBe('{"entry_point":true}');
    assertSecurityEntryPointBaseline($response);
});

it('アプリ独自の応答ポリシーに競合するフォールバックを残さない', function (array $ini) {
    $response = securityEntryPointResponse(
        fn (string $directory) => bootSecurityEntryPoint($directory, [
            'content-security-policy' => "frame-ancestors 'self'; form-action https://payments.example.test",
            'x-frame-options' => 'SAMEORIGIN',
            'referrer-policy' => 'no-referrer',
        ]),
        '/_testing/entry-point',
        ini: $ini,
    );

    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeader('Content-Security-Policy'))
        ->toBe(["frame-ancestors 'self'; form-action https://payments.example.test"]);
    expect($response->getHeader('X-Frame-Options'))->toBe(['SAMEORIGIN']);
    expect($response->getHeader('Referrer-Policy'))->toBe(['no-referrer']);
    expect($response->getHeader('X-Content-Type-Options'))->toBe(['nosniff']);
})->with([
    '通常のPHP設定' => [[]],
    'header_removeが無効' => [['disable_functions=header_remove']],
]);

it('事前描画メンテナンス中もJSON要求はLaravelの503へ引き継ぐ', function () {
    $response = securityEntryPointResponse(function (string $directory): void {
        bootSecurityEntryPoint($directory);
        File::copy(
            base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub'),
            $directory.'/storage/framework/maintenance.php',
        );
        File::put($directory.'/storage/framework/down', json_encode([
            'template' => '<h1>Maintenance fixture</h1>',
            'status' => 503,
            'retry' => 60,
        ], JSON_THROW_ON_ERROR));
    }, '/_testing/entry-point', ['Accept' => 'application/json']);

    expect($response->getStatusCode())->toBe(503);
    expect(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['message' => 'Service Unavailable']);
    expect($response->getHeader('Retry-After'))->toBe(['60']);
    assertSecurityEntryPointBaseline($response);
});

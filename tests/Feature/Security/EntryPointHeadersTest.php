<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

/**
 * 本物のエントリポイントを HTTP SAPI で動かし、メンテナンスファイルと子プロセスの書き込みはフィクスチャ内に閉じる。
 */
function securityEntryPointResponse(Closure $prepare, string $path = '/', array $headers = [], array $ini = [], array $env = []): ResponseInterface
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
            ...$env,
        ], timeout: 60);

        $server->start();
        $output = '';
        $started = $server->waitUntil(function (string $type, string $buffer) use (&$output, $address): bool {
            $output .= $buffer;

            return str_contains($output, "(http://{$address}) started");
        });

        // 選んだポートは候補にすぎないので、別プロセスの応答を受け取らない。
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

// 契約値は独立したリテラルで持つ。配列での比較はヘッダーフィールドの重複も弾く。
function assertSecurityEntryPointBaseline(ResponseInterface $response): void
{
    expect($response->getHeader('Content-Security-Policy'))
        ->toBe(["frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'"]);
    expect($response->getHeader('X-Content-Type-Options'))->toBe(['nosniff']);
    expect($response->getHeader('Referrer-Policy'))->toBe(['strict-origin-when-cross-origin']);
    expect($response->getHeader('X-Frame-Options'))->toBe(['DENY']);
}

// Laravel が付ける CSP は script-src を足した 1 つだけで、起動前の基本ポリシーと重複しない。
function assertLaravelEntryPointHeaders(ResponseInterface $response): void
{
    expect($response->getHeader('Content-Security-Policy'))->toHaveCount(1)
        ->and($response->getHeaderLine('Content-Security-Policy'))
        ->toStartWith("frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; script-src 'nonce-");
    expect($response->getHeader('X-Content-Type-Options'))->toBe(['nosniff']);
    expect($response->getHeader('Referrer-Policy'))->toBe(['strict-origin-when-cross-origin']);
    expect($response->getHeader('X-Frame-Options'))->toBe(['DENY']);
}

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

function prepareSecurityEntryPointMaintenance(string $directory): void
{
    File::copy(
        base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub'),
        $directory.'/storage/framework/maintenance.php',
    );
    File::put($directory.'/storage/framework/down', json_encode([
        'template' => '<h1>Maintenance fixture</h1>',
        'status' => 503,
        'retry' => 60,
    ], JSON_THROW_ON_ERROR));
}

it('事前描画メンテナンスの503にも基本のヘッダーを一つずつ返す', function () {
    $response = securityEntryPointResponse(prepareSecurityEntryPointMaintenance(...));

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
    assertLaravelEntryPointHeaders($response);
});

it('expose_php が有効でも X-Powered-By を露出しない', function (Closure $prepare, string $path, int $status) {
    $response = securityEntryPointResponse($prepare, $path, ini: ['expose_php=1']);

    expect($response->getStatusCode())->toBe($status);
    expect($response->getHeader('X-Powered-By'))->toBe([]);
})->with([
    '通常応答' => [fn (string $directory) => bootSecurityEntryPoint($directory), '/_testing/entry-point', 200],
    '事前描画メンテナンスの503' => [prepareSecurityEntryPointMaintenance(...), '/', 503],
    '初期化に失敗した500' => [function (string $directory): void {
        File::put($directory.'/vendor/autoload.php', '<?php throw new RuntimeException("Initialization fixture failure");');
    }, '/', 500],
]);

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
        prepareSecurityEntryPointMaintenance($directory);
    }, '/_testing/entry-point', ['Accept' => 'application/json']);

    expect($response->getStatusCode())->toBe(503);
    expect(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['message' => 'Service Unavailable']);
    expect($response->getHeader('Retry-After'))->toBe(['60']);
    assertLaravelEntryPointHeaders($response);
});

it('Cloudflare がオリジンの照合に付けた秘密を、照合の成否によらず Laravel とリクエスト変数へ渡さない', function (string $digest, string $ip) {
    // CGI と FastCGI は、variables_order が E を含むとリクエストの変数を $_ENV にも入れる。
    $response = securityEntryPointResponse(function (string $directory) use ($digest): void {
        bootSecurityEntryPoint($directory);
        File::put($directory.'/public/index.php', str_replace(
            '{CLOUDFLARE_PROXY_SECRET_SHA1}',
            $digest,
            File::get($directory.'/public/index.php'),
        ));
        File::put($directory.'/bootstrap/app.php', str_replace(
            "['entry_point' => true]",
            "['header' => request()->headers->has('X-Cloudflare-Proxy-Secret'), 'server' => array_key_exists('HTTP_X_CLOUDFLARE_PROXY_SECRET', \$_SERVER), 'env' => array_key_exists('HTTP_X_CLOUDFLARE_PROXY_SECRET', \$_ENV), 'other' => request()->headers->get('X-Other'), 'ip' => request()->ip()]",
            File::get($directory.'/bootstrap/app.php'),
        ));
    }, '/_testing/entry-point', ['X-Cloudflare-Proxy-Secret' => 'zone-secret', 'CF-Connecting-IP' => '198.51.100.7', 'X-Other' => 'kept'], ['variables_order=EGPCS'], ['HTTP_X_CLOUDFLARE_PROXY_SECRET' => 'zone-secret']);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['header' => false, 'server' => false, 'env' => false, 'other' => 'kept', 'ip' => $ip]);
})->with([
    '照合に成功' => [sha1('zone-secret'), '198.51.100.7'],
    '配備済みの SHA-1 と不一致' => [sha1('other-secret'), '127.0.0.1'],
]);

it('Cloudflare の秘密が配備で書き込んだ SHA-1 と一致する要求だけ、CF-Connecting-IP を接続元にする', function (string $digest, array $headers, string $ip) {
    $response = securityEntryPointResponse(function (string $directory) use ($digest): void {
        bootSecurityEntryPoint($directory);
        File::put($directory.'/public/index.php', str_replace(
            '{CLOUDFLARE_PROXY_SECRET_SHA1}',
            $digest,
            File::get($directory.'/public/index.php'),
        ));
        File::put($directory.'/bootstrap/app.php', str_replace(
            "['entry_point' => true]",
            "['ip' => request()->ip()]",
            File::get($directory.'/bootstrap/app.php'),
        ));
    }, '/_testing/entry-point', $headers);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR))->toBe(['ip' => $ip]);
})->with([
    'IPv6 の利用者' => [sha1('zone-secret'), ['X-Cloudflare-Proxy-Secret' => 'zone-secret', 'CF-Connecting-IP' => '2001:db8::7'], '2001:db8::7'],
    '秘密なし' => [sha1('zone-secret'), ['CF-Connecting-IP' => '198.51.100.7'], '127.0.0.1'],
    '配備で SHA-1 を書き込む前' => ['{CLOUDFLARE_PROXY_SECRET_SHA1}', ['X-Cloudflare-Proxy-Secret' => 'zone-secret', 'CF-Connecting-IP' => '198.51.100.7'], '127.0.0.1'],
    'IP として読めない値' => [sha1('zone-secret'), ['X-Cloudflare-Proxy-Secret' => 'zone-secret', 'CF-Connecting-IP' => 'unknown'], '127.0.0.1'],
    '複数の値' => [sha1('zone-secret'), ['X-Cloudflare-Proxy-Secret' => 'zone-secret', 'CF-Connecting-IP' => '198.51.100.7, 203.0.113.1'], '127.0.0.1'],
]);

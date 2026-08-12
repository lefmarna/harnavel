<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

describe('リバースプロキシ', function () {
    it('ローカル環境では直前のproxyが伝えたHTTPS schemeだけを信頼する', function () {
        expect(proxyRequestMetadata('local'))->toBe([
            'secure' => true,
            'url' => 'https://dev-machine.example.ts.net/probe',
            'host' => 'dev-machine.example.ts.net',
            'clientIp' => '172.18.0.2',
        ]);
    });

    it('本番環境ではクライアントが転送ヘッダーを偽装しても信頼しない', function () {
        expect(proxyRequestMetadata('production'))->toBe([
            'secure' => false,
            'url' => 'http://dev-machine.example.ts.net/probe',
            'host' => 'dev-machine.example.ts.net',
            'clientIp' => '172.18.0.2',
        ]);
    });

    it('ローカル環境でもNginxを外部公開した場合は転送ヘッダーを信頼しない', function () {
        expect(proxyRequestMetadata('local', '0.0.0.0'))->toBe([
            'secure' => false,
            'url' => 'http://dev-machine.example.ts.net/probe',
            'host' => 'dev-machine.example.ts.net',
            'clientIp' => '172.18.0.2',
        ]);
    });
});

/**
 * @return array{secure: bool, url: string, host: string, clientIp: string|null}
 */
function proxyRequestMetadata(string $environment, string $dockerBindAddress = '127.0.0.1'): array
{
    $script = <<<'PHP'
    require 'vendor/autoload.php';

    $app = require 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create(
        'http://dev-machine.example.ts.net/up',
        'GET',
        server: [
            'REMOTE_ADDR' => '172.18.0.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25',
            'HTTP_X_FORWARDED_HOST' => 'spoofed.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    );

    $response = $kernel->handle($request);

    echo json_encode([
        'secure' => $request->secure(),
        'url' => url('/probe'),
        'host' => $request->host(),
        'clientIp' => $request->getClientIp(),
    ], JSON_THROW_ON_ERROR);

    $kernel->terminate($request, $response);
    PHP;

    $process = new Process(
        [PHP_BINARY, '-r', $script],
        base_path(),
        [
            'APP_ENV' => $environment,
            'DOCKER_BIND_ADDRESS' => $dockerBindAddress,
        ],
    );
    $process->mustRun();

    $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($metadata)->toBeArray();

    /** @var array{secure: bool, url: string, host: string, clientIp: string|null} $metadata */
    return $metadata;
}

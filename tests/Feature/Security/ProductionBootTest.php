<?php

declare(strict_types=1);

use Tests\Support\AppSubprocess;

describe('本番環境の起動時検査', function () {
    it('APP_DEBUG が有効だと起動しない', function (string $environment) {
        $process = AppSubprocess::php(
            'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();',
            AppSubprocess::productionEnvironment(['APP_ENV' => $environment, 'APP_DEBUG' => 'true']),
        );

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput().$process->getErrorOutput())->toContain('APP_DEBUG');
    })->with(['production', 'develop']);

    it('APP_DEBUG が有効な HTTP リクエストでも拒否理由と内部情報を返さない', function () {
        $script = <<<'PHP'
        require 'vendor/autoload.php';

        $app = require 'bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $request = Illuminate\Http\Request::create('https://example.com/up', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $kernel->handle($request);

        echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent()], JSON_THROW_ON_ERROR);
        PHP;

        $process = AppSubprocess::php($script, AppSubprocess::productionEnvironment([
            'APP_URL' => 'https://example.com',
            'APP_DEBUG' => 'true',
        ]));

        expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());

        /** @var array{status: int, body: string} $result */
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        expect($result['status'])->toBe(500)
            ->and($result['body'])->not->toContain('APP_DEBUG')
            ->not->toContain('AppServiceProvider')
            ->not->toContain('trace');
    });

    it('APP_URL のホスト以外へのリクエストを拒否する', function (string $host, int $expectedStatus) {
        expect(productionStatusForHost($host))->toBe($expectedStatus);
    })->with([
        'APP_URL のホスト' => ['example.com', 200],
        'APP_URL のサブドメイン' => ['dev.example.com', 400],
        '無関係なホスト' => ['attacker.example', 400],
    ]);
});

function productionStatusForHost(string $host): int
{
    $script = <<<'PHP'
    require 'vendor/autoload.php';

    $app = require 'bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create('https://'.getenv('PROBE_HOST').'/up');
    $response = $kernel->handle($request);

    echo $response->getStatusCode();

    $kernel->terminate($request, $response);
    PHP;

    $process = AppSubprocess::php($script, AppSubprocess::productionEnvironment([
        'APP_URL' => 'https://example.com',
        'PROBE_HOST' => $host,
    ]));

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());

    return (int) $process->getOutput();
}

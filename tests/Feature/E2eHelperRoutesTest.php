<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

// ルート定義はブート時に一度しか評価されないので、環境変数を変えた別プロセスで観測する。
function registeredE2eRoutePaths(?string $helperRoutes, string $appEnv = 'local'): array
{
    $environment = ['APP_ENV' => $appEnv];

    if ($helperRoutes !== null) {
        $environment['E2E_HELPER_ROUTES'] = $helperRoutes;
    }

    $process = new Process(
        [PHP_BINARY, 'artisan', 'route:list', '--json'],
        base_path(),
        $environment,
    );
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    /** @var list<array{uri: string}> $routes */
    $routes = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    return array_values(array_filter(
        array_column($routes, 'uri'),
        static fn (string $uri): bool => str_starts_with($uri, '_e2e/'),
    ));
}

describe('E2Eヘルパールートの有効化契約', function () {
    it('E2E_HELPER_ROUTESが未設定ならlocalでもヘルパールートを登録しない', function () {
        expect(registeredE2eRoutePaths(null))->toBeEmpty();
    });

    it('E2E_HELPER_ROUTESを有効にした非productionではヘルパールートを登録する', function () {
        expect(registeredE2eRoutePaths('true'))->not->toBeEmpty();
    });

    it('productionではE2E_HELPER_ROUTESが有効でもヘルパールートを登録しない', function () {
        expect(registeredE2eRoutePaths('true', appEnv: 'production'))->toBeEmpty();
    });

    it('ヘルパールートの定義はroutes/e2e.phpにだけ存在する', function () {
        expect(File::get(base_path('routes/web.php')))->not->toContain('_e2e')
            ->and(File::get(base_path('routes/e2e.php')))->toContain('_e2e');
    });

    it('テスト実行中のアプリケーションではヘルパールートが無効になっている', function () {
        expect(config('e2e.helper_routes'))->toBeFalse()
            ->and(Route::has('e2e.auth.login-verified'))->toBeFalse();
    });

    // フラグが閉じている限り不活性だが、誤設定時の壊れ方を「露出」ではなく「即死」にする。
    // config/e2e.php は送るので require が失敗し、post-deploy の route:cache が
    // artisan up より前に落ちて maintenance が維持される。
    it('ヘルパールートの定義はデプロイパッケージから除外されている', function () {
        $deployIgnore = File::lines(base_path('.deployignore'))
            ->map(fn (string $line): string => trim($line))
            ->all();

        expect($deployIgnore)->toContain('routes/e2e.php')
            ->and($deployIgnore)->not->toContain('config/e2e.php');
    });
});

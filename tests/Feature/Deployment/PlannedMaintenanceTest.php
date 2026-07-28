<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

function enablePlannedDeploymentMaintenance(string $deployDirectory): Process
{
    $process = new Process([PHP_BINARY], $deployDirectory);
    $process->setInput(File::get(base_path('.github/deploy/enable-maintenance.php')));
    $process->run();

    return $process;
}

/**
 * @param  array<string, string>  $serverHeaders
 */
function requestDeploymentFrontController(
    string $frontController,
    string $uri,
    array $serverHeaders = [],
): Process {
    $command = [
        PHP_BINARY,
        '-r',
        <<<'PHP'
$_SERVER['REQUEST_URI'] = $argv[2];
foreach (json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR) as $name => $value) {
    $_SERVER[$name] = $value;
}
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS:'.http_response_code());
});
require $argv[1];
echo 'BOOTSTRAP_REACHED';
PHP,
        $frontController,
        $uri,
        json_encode($serverHeaders, JSON_THROW_ON_ERROR),
    ];

    $process = new Process($command);
    $process->run();

    return $process;
}

describe('計画メンテナンス', function () {
    it('空のデプロイ先でも現在のアプリを起動せず静的503を準備できる', function () {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-empty-deploy-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory);

        try {
            expect($deployDirectory.'/artisan')->not->toBeFile();

            $process = enablePlannedDeploymentMaintenance($deployDirectory);

            expect($process->isSuccessful())
                ->toBeTrue($process->getErrorOutput())
                ->and($deployDirectory.'/storage/framework/down')->toBeFile()
                ->and($deployDirectory.'/storage/framework/maintenance.php')->toBeFile();
        } finally {
            File::deleteDirectory($deployDirectory);
        }
    });

    it('部分更新されたデプロイ先でも既存ツリーを起動せず静的503を再生成できる', function () {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-partial-deploy-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory.'/storage/framework');
        File::put(
            $deployDirectory.'/artisan',
            '<?php file_put_contents(__DIR__.\'/OLD_APPLICATION_BOOTED\', \'1\');',
        );
        File::put($deployDirectory.'/storage/framework/down', '{}');
        File::put($deployDirectory.'/storage/framework/maintenance.php', '<?php return;');

        try {
            $process = enablePlannedDeploymentMaintenance($deployDirectory);
            $maintenance = json_decode(
                File::get($deployDirectory.'/storage/framework/down'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            expect($process->isSuccessful())
                ->toBeTrue($process->getErrorOutput())
                ->and($deployDirectory.'/OLD_APPLICATION_BOOTED')->not->toBeFile()
                ->and($maintenance)->toMatchArray(['retry' => 60, 'status' => 503])
                ->and(File::get($deployDirectory.'/storage/framework/maintenance.php'))
                ->toContain('現在メンテナンス中です');
        } finally {
            File::deleteDirectory($deployDirectory);
        }
    });

    it('JSONとヘルスチェックも依存関係を読み込む前に503で遮断できる', function () {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-maintenance-response-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory.'/public');
        File::copy(base_path('public/index.php'), $deployDirectory.'/public/index.php');

        expect($deployDirectory.'/vendor/autoload.php')->not->toBeFile();

        try {
            $enableMaintenance = enablePlannedDeploymentMaintenance($deployDirectory);

            expect($enableMaintenance->isSuccessful())->toBeTrue($enableMaintenance->getErrorOutput());

            $frontController = $deployDirectory.'/public/index.php';
            $jsonResponse = requestDeploymentFrontController(
                $frontController,
                '/two-factor-authentication',
                ['HTTP_ACCEPT' => 'application/json'],
            );
            $healthResponse = requestDeploymentFrontController(
                $frontController,
                '/up',
                ['HTTP_ACCEPT' => 'text/html'],
            );

            expect($jsonResponse->getOutput())
                ->toBe('{"message":"Service Unavailable"}')
                ->not->toContain('BOOTSTRAP_REACHED')
                ->and($jsonResponse->getErrorOutput())->toBe('STATUS:503')
                ->and($healthResponse->getOutput())
                ->toContain('現在メンテナンス中です')
                ->not->toContain('BOOTSTRAP_REACHED')
                ->and($healthResponse->getErrorOutput())->toBe('STATUS:503');
        } finally {
            File::deleteDirectory($deployDirectory);
        }
    });

    it('InertiaのXHR遷移でも依存関係を読み込む前に静的HTMLの503を返せる', function () {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-maintenance-inertia-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory.'/public');
        File::copy(base_path('public/index.php'), $deployDirectory.'/public/index.php');

        try {
            $enableMaintenance = enablePlannedDeploymentMaintenance($deployDirectory);

            expect($enableMaintenance->isSuccessful())->toBeTrue($enableMaintenance->getErrorOutput());

            $response = requestDeploymentFrontController(
                $deployDirectory.'/public/index.php',
                '/dashboard',
                [
                    'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_X_INERTIA' => 'true',
                ],
            );

            expect($response->getOutput())
                ->toContain('現在メンテナンス中です')
                ->not->toContain('Service Unavailable')
                ->not->toContain('BOOTSTRAP_REACHED')
                ->and($response->getErrorOutput())->toBe('STATUS:503');
        } finally {
            File::deleteDirectory($deployDirectory);
        }
    });

    // TADR-0006 のコンテンツネゴシエーション契約表。
    it('Acceptの全media rangeを解釈して静的JSONと静的HTMLを出し分ける', function (?string $accept, bool $isXhr, string $expected) {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-maintenance-accept-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory.'/public');
        File::copy(base_path('public/index.php'), $deployDirectory.'/public/index.php');

        try {
            $enableMaintenance = enablePlannedDeploymentMaintenance($deployDirectory);

            expect($enableMaintenance->isSuccessful())->toBeTrue($enableMaintenance->getErrorOutput());

            $headers = $accept === null ? [] : ['HTTP_ACCEPT' => $accept];

            if ($isXhr) {
                $headers['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            }

            $response = requestDeploymentFrontController(
                $deployDirectory.'/public/index.php',
                '/dashboard',
                $headers,
            );

            expect($response->isSuccessful())
                ->toBeTrue($response->getErrorOutput())
                ->and($response->getErrorOutput())->toBe('STATUS:503')
                ->and($response->getOutput())->not->toContain('BOOTSTRAP_REACHED');

            $expected === 'json'
                ? expect($response->getOutput())->toBe('{"message":"Service Unavailable"}')
                : expect($response->getOutput())->toContain('現在メンテナンス中です');
        } finally {
            File::deleteDirectory($deployDirectory);
        }
    })->with([
        'JSONが後方にある複数指定はJSON' => ['text/plain, application/json', false, 'json'],
        'q値でHTMLが上回るならHTML' => ['application/json;q=0.1, text/html;q=0.9', false, 'html'],

        '非対応typeと低いqのJSONならJSON' => ['text/plain, application/json;q=0.5', false, 'json'],
        '非対応typeが高いqでも対応typeが勝つ' => ['text/plain;q=0.9, text/html;q=0.5', false, 'html'],

        'JSONとHTMLが同率ならHTML' => ['application/json, text/html', false, 'html'],
        'ヘッダー順を入れ替えても同率ならHTML' => ['text/html, application/json', false, 'html'],
        '小数の同率でもHTML' => ['text/html;q=0.5, application/json;q=0.5', false, 'html'],

        '具体的なJSONはワイルドカードのHTMLに優先する' => ['application/json, */*', false, 'json'],
        'サブタイプワイルドカードのtextはHTML' => ['text/*', false, 'html'],
        'サブタイプワイルドカードのapplicationはJSON' => ['application/*', false, 'json'],
        '完全ワイルドカードはHTML' => ['*/*', false, 'html'],
        '+json サフィックスもJSONとして扱う' => ['application/vnd.api+json', false, 'json'],
        'より具体的なrangeのq=0が緩いrangeを上書きする' => ['text/html;q=0, text/*', false, 'json'],

        'HTMLをq=0で明示拒否したらJSON' => ['text/html;q=0', false, 'json'],

        '未知のtypeだけならHTML' => ['text/plain', false, 'html'],
        'Accept未設定ならHTML' => [null, false, 'html'],
        'Acceptが空文字ならHTML' => ['', false, 'html'],

        'ワイルドカードのみのXHRはJSON' => ['*/*', true, 'json'],
        '対応形式がない指定はXHRでもHTML' => ['text/plain', true, 'html'],
        'HTMLをq=0で拒否したXHRはJSON' => ['text/html;q=0', true, 'json'],
        'Accept未設定のXHRはJSON' => [null, true, 'json'],
        'InertiaのXHRはHTMLを明示するのでHTML' => ['text/html, application/xhtml+xml', true, 'html'],
    ]);

    it('新しいリリースのartisan upで静的503とmaintenance状態を解除できる', function () {
        $deployDirectory = sys_get_temp_dir().'/laravel-app-template-artisan-up-'.bin2hex(random_bytes(8));

        File::ensureDirectoryExists($deployDirectory);

        try {
            $enableMaintenance = enablePlannedDeploymentMaintenance($deployDirectory);

            expect($enableMaintenance->isSuccessful())->toBeTrue($enableMaintenance->getErrorOutput())
                ->and($deployDirectory.'/storage/framework/down')->toBeFile()
                ->and($deployDirectory.'/storage/framework/maintenance.php')->toBeFile();

            $maintenance = json_decode(
                File::get($deployDirectory.'/storage/framework/down'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            expect($maintenance)
                ->toMatchArray(['retry' => 60, 'status' => 503])
                ->and($maintenance['template'])
                ->toContain('現在メンテナンス中です')
                ->not->toContain('問い合わせID');

            // 一時 storage を向けた別プロセスで実行し、実アプリの状態を変えずに検証する。
            $upProcess = new Process(
                [PHP_BINARY, base_path('artisan'), 'up'],
                base_path(),
                ['LARAVEL_STORAGE_PATH' => $deployDirectory.'/storage'],
            );
            $upProcess->run();

            expect($upProcess->isSuccessful())
                ->toBeTrue($upProcess->getErrorOutput())
                ->and($deployDirectory.'/storage/framework/down')->not->toBeFile()
                ->and($deployDirectory.'/storage/framework/maintenance.php')->not->toBeFile();
        } finally {
            File::deleteDirectory($deployDirectory);
        }

        expect(storage_path('framework/down'))->not->toBeFile()
            ->and(storage_path('framework/maintenance.php'))->not->toBeFile();
    });
});

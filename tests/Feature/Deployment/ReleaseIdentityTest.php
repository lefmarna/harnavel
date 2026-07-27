<?php

declare(strict_types=1);

use App\Http\Controllers\ReleaseHealthController;
use App\Support\OpcacheRevalidation;
use App\Support\ReleaseIdentity;
use App\Support\ReleaseVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/**
 * PHP 定数は同じプロセス内で未定義・空・有効値へ戻せない。リリースID解決は
 * フロントコントローラが define した定数だけを参照するため、常に別プロセスで検証する。
 *
 * @param  list<string>  $arguments
 * @param  list<string>  $phpOptions
 * @param  array<string, string>  $env
 */
function runIsolatedPhp(
    string $script,
    array $arguments,
    array $phpOptions = [],
    array $env = [],
): Process {
    $process = new Process(
        [PHP_BINARY, ...$phpOptions, '-r', $script, ...$arguments],
        env: $env,
    );
    $process->run();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function decodeIsolatedPhpJson(Process $process): array
{
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

    $decoded = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * フロントコントローラ相当の define を行った別プロセスでリリースIDを解決する。
 * $releaseId が null のときは定数そのものを定義しない。
 */
function resolveReleaseIdentityInIsolation(?string $releaseId): ?string
{
    $script = <<<'PHP'
    require $argv[1];

    if ($argv[2] !== '__undefined__') {
        define('LARAVEL_DEPLOYMENT_RELEASE_ID', $argv[2]);
    }

    echo json_encode(
        ['id' => App\Support\ReleaseIdentity::fromFrontController()->id()],
        JSON_THROW_ON_ERROR,
    );
    PHP;

    $decoded = decodeIsolatedPhpJson(runIsolatedPhp($script, [
        base_path('vendor/autoload.php'),
        $releaseId ?? '__undefined__',
    ]));

    $id = $decoded['id'] ?? null;

    expect($id)->toBeIn([null, $releaseId]);

    return is_string($id) ? $id : null;
}

/**
 * アプリを別プロセスで起動し、リリース同一性エンドポイントの応答を取得する。
 * route cache を使った経路を実際に通すため、テストプロセスの解決済みルートには依存しない。
 *
 * @param  array<string, string>  $env
 * @return array{status: int, body: string}
 */
function requestReleaseHealthInIsolation(string $releaseId, array $env = []): array
{
    $script = <<<'PHP'
    define('LARAVEL_DEPLOYMENT_RELEASE_ID', $argv[2]);

    require $argv[1];

    /** @var Illuminate\Foundation\Application $app */
    $app = require $argv[3];

    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)
        ->handle(Illuminate\Http\Request::create('/up/release'));

    echo json_encode(
        ['status' => $response->getStatusCode(), 'body' => (string) $response->getContent()],
        JSON_THROW_ON_ERROR,
    );
    PHP;

    $decoded = decodeIsolatedPhpJson(runIsolatedPhp(
        $script,
        [base_path('vendor/autoload.php'), $releaseId, base_path('bootstrap/app.php')],
        env: $env,
    ));

    return [
        'status' => is_int($decoded['status']) ? $decoded['status'] : 0,
        'body' => is_string($decoded['body']) ? $decoded['body'] : '',
    ];
}

function fakeReleaseIdentity(?string $releaseId): void
{
    app()->instance(ReleaseIdentity::class, new ReleaseIdentity($releaseId));
}

function fakeOpcacheRevalidation(OpcacheRevalidation $revalidation): void
{
    app()->instance(OpcacheRevalidation::class, $revalidation);
}

function safeOpcacheRevalidation(): OpcacheRevalidation
{
    return new OpcacheRevalidation(
        extensionLoaded: true,
        enabled: true,
        validateTimestamps: true,
        revalidateFrequency: 2,
        preloadConfigured: false,
    );
}

describe('リリース同一性', function () {
    describe('リリースIDの解決', function () {
        it('フロントコントローラが定義した定数のリリースIDを返す', function () {
            expect(resolveReleaseIdentityInIsolation('release-a'))->toBe('release-a');
        });

        it('補助ファイルが別のリリースIDを持っていてもフロントコントローラ定数の値を返す', function () {
            $auxiliary = base_path('bootstrap/release.php');

            expect($auxiliary)->not->toBeFile();

            File::put($auxiliary, "<?php\n\nreturn 'release-b';\n");

            try {
                expect(resolveReleaseIdentityInIsolation('release-a'))->toBe('release-a');
            } finally {
                File::delete($auxiliary);
            }
        });

        it('フロントコントローラ定数が未定義の場合はリリースIDを解決しない', function () {
            expect(resolveReleaseIdentityInIsolation(null))->toBeNull();
        });

        it('フロントコントローラ定数が空文字の場合はリリースIDを解決しない', function () {
            expect(resolveReleaseIdentityInIsolation(''))->toBeNull();
        });

        it('未定義・空文字・非文字列はいずれも未確定として正規化する', function (mixed $value) {
            expect(ReleaseIdentity::fromValue($value)->id())->toBeNull();
        })->with([
            '未定義' => null,
            '空文字' => '',
            '真偽値' => false,
            '数値' => 0,
        ]);
    });

    describe('リリース同一性エンドポイント', function () {
        it('リリースIDを解決できる場合は200でリリースIDを返す', function () {
            fakeReleaseIdentity('release-a');
            fakeOpcacheRevalidation(safeOpcacheRevalidation());

            $this->get('/up/release')
                ->assertOk()
                ->assertExactJson([
                    'release' => 'release-a',
                    'opcache' => [
                        'extensionLoaded' => true,
                        'enabled' => true,
                        'validateTimestamps' => true,
                        'revalidateFrequency' => 2,
                        'preloadConfigured' => false,
                    ],
                ]);
        });

        it('リリースIDを解決できない場合は503を返す', function () {
            fakeReleaseIdentity(null);
            fakeOpcacheRevalidation(safeOpcacheRevalidation());

            $this->get('/up/release')
                ->assertServiceUnavailable()
                ->assertJsonPath('release', null);
        });

        it('OPcache設定が同一性を保証できない場合は503でも観測値を返す', function (OpcacheRevalidation $revalidation) {
            fakeReleaseIdentity('release-a');
            fakeOpcacheRevalidation($revalidation);

            $this->get('/up/release')
                ->assertServiceUnavailable()
                ->assertJsonPath('release', 'release-a')
                ->assertJsonPath('opcache.enabled', $revalidation->enabled);
        })->with([
            'タイムスタンプ再検証が無効' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, false, 2, false),
            'preload が設定済み' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, true, 2, true),
            '再検証間隔が上限超過' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, true, 61, false),
            'ini 値が解釈不能' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, null, true, 2, false),
        ]);

        it('応答に実行中PHPランタイムのOPcache再検証設定が含まれる', function () {
            fakeReleaseIdentity('release-a');

            $this->get('/up/release')
                ->assertJsonPath('opcache.extensionLoaded', extension_loaded('Zend OPcache'));
        });

        // 認証なしの公開エンドポイントなので、起動時スクリプトの絶対パスは載せない。
        // ワークフローが必要とするのは「preload が設定されているか」だけ。
        it('preloadの絶対パスを公開せず設定有無だけを返す', function () {
            fakeReleaseIdentity('release-a');
            fakeOpcacheRevalidation(
                OpcacheRevalidation::fromIniValues(true, '1', '1', '2', '/var/www/preload.php'),
            );

            $response = $this->get('/up/release');

            $response->assertJsonPath('opcache.preloadConfigured', true);

            expect($response->getContent())->not->toContain('/var/www/preload.php');
        });

        it('応答を保存させないキャッシュ制御ヘッダーを返す', function () {
            fakeReleaseIdentity('release-a');
            fakeOpcacheRevalidation(safeOpcacheRevalidation());

            $response = $this->get('/up/release');

            expect($response->headers->get('Cache-Control'))
                ->toContain('no-store')
                ->toContain('no-cache')
                ->and($response->headers->get('Pragma'))->toBe('no-cache');
        });

        it('他のルートに隠されず専用コントローラへ解決される', function () {
            $route = Route::getRoutes()->match(Request::create('/up/release'));

            expect($route->getActionName())->toBe(ReleaseHealthController::class);
        });

        it('route:cache 後もリリースIDを返せる', function () {
            // 共有の bootstrap/cache を汚すと、並行して動く開発サーバーや E2E が
            // testing 環境のルートで動いてしまう。APP_ROUTES_CACHE で退避先を分ける。
            $routesCache = sys_get_temp_dir()
                .'/laravel-app-template-routes-'.bin2hex(random_bytes(8)).'.php';

            try {
                $cache = new Process(
                    [PHP_BINARY, 'artisan', 'route:cache'],
                    base_path(),
                    ['APP_ROUTES_CACHE' => $routesCache],
                );
                $cache->run();

                expect($cache->isSuccessful())
                    ->toBeTrue($cache->getErrorOutput())
                    ->and($routesCache)->toBeFile();

                $response = requestReleaseHealthInIsolation(
                    'release-cached',
                    ['APP_ROUTES_CACHE' => $routesCache],
                );

                expect($response['status'])
                    ->toBe(200)
                    ->and($response['body'])->toContain('release-cached');
            } finally {
                File::delete($routesCache);
            }
        });
    });

    describe('OPcache再検証の判定', function () {
        it('拡張が未ロードなら経過秒数によらず安全と判定する', function () {
            $revalidation = new OpcacheRevalidation(false, false, false, null, null);

            expect(ReleaseVerification::verdict($revalidation, 0))
                ->toBe(ReleaseVerification::VERDICT_SAFE);
        });

        it('OPcacheが無効なら経過秒数によらず安全と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, false, false, null, false);

            expect(ReleaseVerification::verdict($revalidation, 0))
                ->toBe(ReleaseVerification::VERDICT_SAFE);
        });

        it('タイムスタンプ再検証が無効なら失敗と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, false, 2, false);

            expect(ReleaseVerification::verdict($revalidation, 999))
                ->toBe(ReleaseVerification::VERDICT_FAIL);
        });

        it('preloadが設定されていれば失敗と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, true, 2, true);

            expect(ReleaseVerification::verdict($revalidation, 999))
                ->toBe(ReleaseVerification::VERDICT_FAIL);
        });

        it('再検証間隔が上限を超える場合は失敗と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, true, 61, false);

            expect(ReleaseVerification::verdict($revalidation, 999))
                ->toBe(ReleaseVerification::VERDICT_FAIL);
        });

        it('再検証間隔が上限ちょうどなら経過後に安全と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, true, 60, false);

            expect(ReleaseVerification::verdict($revalidation, 60))
                ->toBe(ReleaseVerification::VERDICT_SAFE)
                ->and(ReleaseVerification::verdict($revalidation, 59))
                ->toBe(ReleaseVerification::VERDICT_WAIT);
        });

        it('ini値が解釈できない場合は失敗と判定する', function (OpcacheRevalidation $revalidation) {
            expect(ReleaseVerification::verdict($revalidation, 999))
                ->toBe(ReleaseVerification::VERDICT_FAIL);
        })->with([
            '有効状態が不明' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, null, true, 2, false),
            '再検証設定が不明' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, null, 2, false),
            '再検証間隔が不明' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, true, null, false),
            'preload 設定が不明' => fn (): OpcacheRevalidation => new OpcacheRevalidation(true, true, true, 2, null),
        ]);

        it('再検証間隔が経過していない場合は待機と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, true, 30, false);

            expect(ReleaseVerification::verdict($revalidation, 29))
                ->toBe(ReleaseVerification::VERDICT_WAIT);
        });

        it('経過秒数が不明な場合は待機を判定せず安全と判定する', function () {
            $revalidation = new OpcacheRevalidation(true, true, true, 30, false);

            expect(ReleaseVerification::verdict($revalidation, null))
                ->toBe(ReleaseVerification::VERDICT_SAFE);
        });
    });

    describe('OPcache再検証設定の読み取り', function () {
        it('拡張が未ロードの場合はini値によらず無効として報告する', function () {
            $revalidation = OpcacheRevalidation::fromIniValues(false, '1', '1', '2', '');

            expect($revalidation->toArray())->toBe([
                'extensionLoaded' => false,
                'enabled' => false,
                'validateTimestamps' => false,
                'revalidateFrequency' => null,
                'preloadConfigured' => null,
            ]);
        });

        it('拡張の有無は実行中ランタイムの状態を反映する', function () {
            expect(OpcacheRevalidation::fromRuntime()->extensionLoaded)
                ->toBe(extension_loaded('Zend OPcache'));
        });

        it('実行中のPHPランタイムのini値をそのまま報告する', function () {
            $script = <<<'PHP'
            require $argv[1];

            echo json_encode(
                App\Support\OpcacheRevalidation::fromRuntime()->toArray(),
                JSON_THROW_ON_ERROR,
            );
            PHP;

            $reported = decodeIsolatedPhpJson(runIsolatedPhp(
                $script,
                [base_path('vendor/autoload.php')],
                ['-d', 'opcache.enable=1', '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.revalidate_freq=7'],
            ));

            expect($reported)->toMatchArray([
                'extensionLoaded' => true,
                'enabled' => true,
                'validateTimestamps' => false,
                'revalidateFrequency' => 7,
            ]);
        });

        // PHP が有効と解釈する値を無効と報告すると、ワークフローは「OPcache が無いから
        // 同一性は厳密」と結論づけ、再検証しないランタイムを承認してしまう（fail open）。
        it('PHPのINI真偽値と同じ規則で有効状態を解釈する', function (string $iniValue, ?bool $expected) {
            $revalidation = OpcacheRevalidation::fromIniValues(true, $iniValue, $iniValue, '2', '');

            expect($revalidation->enabled)
                ->toBe($expected)
                ->and($revalidation->validateTimestamps)->toBe($expected);
        })->with([
            '1' => ['1', true],
            '2' => ['2', true],
            '-1' => ['-1', true],
            'On' => ['On', true],
            'yes' => ['yes', true],
            'true' => ['true', true],
            '0' => ['0', false],
            '空文字' => ['', false],
            'Off' => ['Off', false],
            '数値以外' => ['garbage', false],
            // zend_ini_parse_bool は atoi 相当で先頭の整数部だけを読む。PHP の (int) キャストは
            // 指数表記を float 経由で解釈するため "1e-1" を 0 にし、PHP が有効と解釈する値を
            // 無効と報告してしまう。
            '指数表記で小数へ落ちる値' => ['1e-1', true],
            '指数表記で小数へ落ちる値（先頭2）' => ['2e-1', true],
            '指数表記だが先頭が0' => ['0e5', false],
            '先頭が0埋めの非ゼロ' => ['007', true],
            '負の0' => ['-0', false],
            // atoi の戻り値は C int。2^32 の倍数は切り詰めで 0 になり Zend は無効と解釈する。
            // 真偽を確定できない値を true と報告すると、再検証しないランタイムを承認して
            // しまう（fail open）ため、範囲外は null（判定不能）にして fail closed へ倒す。
            'C int 範囲外（2^32）' => ['4294967296', null],
            'C int 範囲外（2^33）' => ['8589934592', null],
            'C int 範囲外（INT_MAX+1）' => ['2147483648', null],
            'INT_MAX ちょうど' => ['2147483647', true],
            'INT_MIN ちょうど' => ['-2147483648', true],
        ]);

        it('preloadは設定有無の真偽値へ正規化する', function (string $iniValue, bool $expected) {
            expect(OpcacheRevalidation::fromIniValues(true, '1', '1', '2', $iniValue)->preloadConfigured)
                ->toBe($expected);
        })->with([
            '未設定' => ['', false],
            '設定済み' => ['/var/www/preload.php', true],
        ]);

        it('ini値が整数として解釈できない場合は再検証間隔を未確定として報告する', function () {
            $revalidation = OpcacheRevalidation::fromIniValues(true, '1', '1', 'never', '');

            expect($revalidation->revalidateFrequency)->toBeNull();
        });

        it('ディレクティブが存在しない場合は未確定として報告する', function () {
            $revalidation = OpcacheRevalidation::fromIniValues(true, false, false, false, false);

            expect($revalidation->enabled)
                ->toBeNull()
                ->and($revalidation->validateTimestamps)->toBeNull()
                ->and($revalidation->revalidateFrequency)->toBeNull()
                ->and($revalidation->preloadConfigured)->toBeNull();
        });
    });
});

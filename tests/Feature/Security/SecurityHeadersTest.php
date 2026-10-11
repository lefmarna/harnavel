<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

mutates(AddSecurityHeaders::class);

beforeEach(function () {
    config()->set('app.debug', false);
    config()->set('services.google.adsense_publisher_id', null);
});

function scriptSrcNonce(TestResponse $response): string
{
    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toMatch(
        "/^frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; script-src 'nonce-([A-Za-z0-9]+)' 'strict-dynamic' https: 'unsafe-inline'( 'unsafe-eval')?$/",
    );
    preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $matches);

    return $matches[1];
}

function assertBaselineSecurityHeaders(TestResponse $response): void
{
    scriptSrcNonce($response);
    $response
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY');
}

it('通常の応答に基本のセキュリティヘッダーを付与する', function () {
    Route::middleware('web')->get('/_testing/security-headers', fn () => response('hello'));

    $response = $this->get('/_testing/security-headers');

    $response->assertOk()
        ->assertContent('hello')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeaderMissing('Permissions-Policy');
    assertBaselineSecurityHeaders($response);
});

it('HTTPSの応答に1年間のHSTSを付与する', function () {
    Route::middleware('web')->get('/_testing/security-headers-https', fn () => response('hello'));

    $response = $this->get('https://localhost/_testing/security-headers-https');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('local環境ではHTTPSの応答にもHSTSを付与しない', function () {
    app()->detectEnvironment(fn (): string => 'local');
    Route::middleware('web')->get('/_testing/security-headers-local', fn () => response('hello'));

    $response = $this->get('https://localhost/_testing/security-headers-local');

    $response->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('HTTPSの応答でもアプリが指定したHSTSを上書きしない', function () {
    Route::middleware('web')->get('/_testing/security-headers-hsts', fn () => response('hello')
        ->header('Strict-Transport-Security', 'max-age=86400; includeSubDomains'));

    $response = $this->get('https://localhost/_testing/security-headers-hsts');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=86400; includeSubDomains');
});

// 秘密を含む画面から移った先の document.referrer 経由で、トークンを計測へ渡さない。
it('秘密を含むURLの画面ではリファラーを送らせない', function (Closure $url) {
    $this->get($url())->assertHeader('Referrer-Policy', 'no-referrer');
})->with([
    'パスワード再設定画面' => [fn (): string => route('password.reset', ['token' => 'secret-token', 'email' => 'user@example.com'])],
    '署名付きのGoogle連携確認画面' => [fn (): string => URL::temporarySignedRoute('auth.google.link.verify', now()->addHour(), ['userId' => '1', 'payload' => 'secret-payload'])],
]);

it('アプリが指定したリファラーポリシーは秘密を含むURLの画面でも上書きしない', function () {
    Route::middleware('web')->get('/_testing/reset/{token}', fn () => response('hello')->header('Referrer-Policy', 'same-origin'));

    $this->get('/_testing/reset/secret-token')->assertHeader('Referrer-Policy', 'same-origin');
});

// ルーティングの前後や web グループの外で応答が作られる経路でも、素通しにしない。
it('通常以外の経路の応答にも基本のヘッダーを付与する', function (Closure $send, int $status) {
    $response = $send($this);

    $response->assertStatus($status);
    assertBaselineSecurityHeaders($response);
})->with([
    'パスの文字コードが不正な400' => [fn ($test) => $test->get('/%FF'), 400],
    'JSON応答' => [function ($test) {
        Route::middleware('web')->get('/_testing/security-headers-json', fn () => response()->json(['ok' => true]));

        return $test->getJson('/_testing/security-headers-json');
    }, 200],
    'ルート内で発生した500' => [function ($test) {
        Route::middleware('web')->get('/_testing/security-headers-error', function (): never {
            abort(500);
        });

        return $test->get('/_testing/security-headers-error');
    }, 500],
    '存在しないパスの404' => [fn ($test) => $test->get('/_testing/security-headers-missing'), 404],
    'webグループ外のヘルスチェック' => [fn ($test) => $test->get('/up'), 200],
    // 巨大な本文は送らず、CONTENT_LENGTH の申告だけで ValidatePostSize を通す。
    'POSTサイズ超過の413' => [fn ($test) => $test->call('POST', '/_testing/security-headers-post', server: ['CONTENT_LENGTH' => (string) PHP_INT_MAX]), 413],
]);

it('実行中のメンテナンスの503にも基本のヘッダーを付与する', function () {
    // artisan down はコンテナ全体を止めるため、中断や並列実行で開発環境を 503 のまま残す。
    // 判定だけを差し替え、このプロセスの外に状態を残さない。
    $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        public function data(): array
        {
            return ['except' => [], 'retry' => 60];
        }
    });

    $response = $this->get('/_testing/security-headers-maintenance');

    $response->assertServiceUnavailable();
    assertBaselineSecurityHeaders($response);
});

it('アプリが明示したポリシーを保持し未指定のヘッダーだけ補う', function () {
    Route::get('/_testing/security-policy', fn () => response('embedded')->withHeaders([
        'Content-Security-Policy' => "frame-ancestors 'self'; form-action https://payments.example.test",
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'no-referrer',
    ]));

    $response = $this->get('/_testing/security-policy');

    $response->assertOk()
        ->assertContent('embedded')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; form-action https://payments.example.test")
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

// 注入されたスクリプトは nonce を持たないので、XSS があっても実行されない（TADR-0012）。
it('ページのスクリプトはリクエストごとの nonce を持つものだけを実行させる', function () {
    $this->app->instance('env', 'production');
    config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
    config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

    $first = $this->get(route('home'));
    $nonce = scriptSrcNonce($first);
    preg_match_all('/<script\b(?![^>]*type="application\/(?:ld\+)?json")[^>]*>/', (string) $first->getContent(), $scripts);

    expect($scripts[0])->not->toBeEmpty()
        ->each->toContain('nonce="'.$nonce.'"')
        ->and(scriptSrcNonce($this->get(route('home'))))->not->toBe($nonce);
});

it('AdSense を設定したときだけ eval を許す', function (?string $publisherId, bool $allowsEval) {
    config()->set('services.google.adsense_publisher_id', $publisherId);

    $policy = (string) $this->get('/up')->headers->get('Content-Security-Policy');

    $allowsEval
        ? expect($policy)->toEndWith("'unsafe-inline' 'unsafe-eval'")
        : expect($policy)->not->toContain('unsafe-eval');
})->with([
    'AdSense あり' => ['ca-pub-1234567890123456', true],
    'AdSense なし' => [null, false],
    'AdSense が空' => ['', false],
]);

// デバッグ時の違反は、報告を拾う E2E で検出する。
it('デバッグ時は script-src を強制せず報告だけにする', function (?int $abortStatus) {
    config()->set('app.debug', true);
    Route::middleware('web')->get('/_testing/security-headers-debug', function () use ($abortStatus) {
        return match ($abortStatus) {
            null => response('page'),
            500 => throw new RuntimeException('debug page'),
            default => abort($abortStatus),
        };
    });

    $response = $this->get('/_testing/security-headers-debug')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'");

    expect($response->headers->get('Content-Security-Policy-Report-Only'))
        ->toMatch("/^script-src 'nonce-[A-Za-z0-9]+' 'strict-dynamic' https: 'unsafe-inline'$/");
})->with([
    '通常の画面' => [null],
    'HTTP 例外でない例外' => [500],
    'エラービューのない HTTP 例外' => [400],
]);

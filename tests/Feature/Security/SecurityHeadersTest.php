<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

mutates(AddSecurityHeaders::class);

function assertBaselineSecurityHeaders(TestResponse $response): void
{
    $response
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'")
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

it('パスの文字コードが不正な400応答にも基本のヘッダーを付与する', function () {
    $response = $this->get('/%FF');

    $response->assertBadRequest();
    assertBaselineSecurityHeaders($response);
});

it('JSON応答にも基本のヘッダーを付与する', function () {
    Route::middleware('web')->get('/_testing/security-headers-json', fn () => response()->json(['ok' => true]));

    $response = $this->getJson('/_testing/security-headers-json');

    $response->assertOk();
    assertBaselineSecurityHeaders($response);
});

it('ルート内で発生した500にも基本のヘッダーを付与する', function () {
    Route::middleware('web')->get('/_testing/security-headers-error', function (): never {
        abort(500);
    });

    $response = $this->get('/_testing/security-headers-error');

    $response->assertInternalServerError();
    assertBaselineSecurityHeaders($response);
});

it('存在しないパスの404にも基本のヘッダーを付与する', function () {
    $response = $this->get('/_testing/security-headers-missing');

    $response->assertNotFound();
    assertBaselineSecurityHeaders($response);
});

// web グループの外にある経路。グローバルに付けていないと、ここだけ素通しになる。
it('webグループ外のヘルスチェックにも基本のヘッダーを付与する', function () {
    $response = $this->get('/up');

    $response->assertOk();
    assertBaselineSecurityHeaders($response);
});

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

it('POSTサイズ超過の413にも基本のヘッダーを付与する', function () {
    // 巨大な本文は送らず、CONTENT_LENGTH の申告だけで ValidatePostSize を通す。
    $response = $this->call(
        'POST',
        '/_testing/security-headers-post',
        server: ['CONTENT_LENGTH' => (string) PHP_INT_MAX],
    );

    $response->assertStatus(413);
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

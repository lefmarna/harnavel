<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Route;
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
    $limit = (int) (ini_get('post_max_size') ?: 0);

    // 巨大な本文は送らず、CONTENT_LENGTH の申告だけで ValidatePostSize を通す。
    $response = $this->call(
        'POST',
        '/_testing/security-headers-post',
        server: ['CONTENT_LENGTH' => (string) (max($limit, 1) * 1024 * 1024 * 1024)],
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

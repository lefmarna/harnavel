<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Support\Facades\Route;

mutates(AddSecurityHeaders::class);

it('通常の応答に基本のセキュリティヘッダーを付与する', function () {
    Route::middleware('web')->get('/_testing/security-headers', fn () => response('hello'));

    $response = $this->get('/_testing/security-headers');

    $response->assertOk()
        ->assertContent('hello')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeaderMissing('Permissions-Policy');
});

it('パスの文字コードが不正な400応答にも基本のヘッダーを付与する', function () {
    $response = $this->get('/%FF');

    $response->assertBadRequest()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY');
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

<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->app->instance('env', 'production');
    config()->set('app.debug', false);
    config()->set('observability.discord.webhook_url', null);

    Route::middleware('web')->get('/_testing/errors/500', function () {
        throw new RuntimeException('secret stack message');
    });

    Route::middleware('web')->get('/_testing/errors/500-with-input', function () {
        throw new RuntimeException('secret stack message');
    });

    Route::middleware('web')->get('/_testing/errors/503', function () {
        abort(503, 'maintenance secret message');
    });
});

describe('本番エラーページ', function () {
    it('500 エラーページに問い合わせIDを表示できる', function () {
        $response = $this->get('/_testing/errors/500');

        $response->assertInternalServerError();

        $requestId = $response->headers->get('X-Request-Id');

        expect($requestId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
        expect($response->getContent())
            ->toContain('問い合わせID')
            ->toContain((string) $requestId);
    });

    it('503 エラーページに問い合わせIDを表示できる', function () {
        $response = $this->get('/_testing/errors/503');

        $response->assertServiceUnavailable();

        $requestId = $response->headers->get('X-Request-Id');

        expect($requestId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
        expect($response->getContent())
            ->toContain('問い合わせID')
            ->toContain((string) $requestId);
    });

    it('本番エラーページにスタックトレースや入力値を表示しない', function () {
        $response = $this->get('/_testing/errors/500-with-input?password=secret-password');

        $response->assertInternalServerError();

        expect($response->getContent())
            ->not->toContain('secret stack message')
            ->not->toContain('secret-password')
            ->not->toContain('Stack trace');
    });
});

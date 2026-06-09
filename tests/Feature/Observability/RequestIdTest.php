<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/_testing/request-id', function (Request $request) {
        return response()->json([
            'request_id' => $request->attributes->get('request_id'),
        ]);
    })->name('testing.request-id');
});

describe('問い合わせID', function () {
    it('web リクエストごとに request_id を割り当てられる', function () {
        $response = $this->getJson('/_testing/request-id');

        $response->assertOk();

        expect($response->json('request_id'))
            ->toBeString()
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });

    it('外部から渡された X-Request-Id を採用せずアプリ側で生成できる', function () {
        $response = $this->withHeader('X-Request-Id', 'client-supplied-id')
            ->getJson('/_testing/request-id');

        $response->assertOk();

        expect($response->json('request_id'))
            ->not->toBe('client-supplied-id')
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });

    it('ログコンテキストに request_id と安全なリクエストメタ情報を共有できる', function () {
        Log::shouldReceive('shareContext')
            ->once()
            ->withArgs(function (array $context): bool {
                expect($context)
                    ->toHaveKeys([
                        'request_id',
                        'http_method',
                        'url',
                        'route',
                        'ip',
                        'user_agent',
                        'user_id',
                    ])
                    ->and($context['request_id'])->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
                    ->and($context['http_method'])->toBe('GET')
                    ->and($context['url'])->toBe(url('/_testing/request-id'))
                    ->and($context['route'])->toBe('testing.request-id')
                    ->and($context['user_id'])->toBeNull();

                expect($context)
                    ->not->toHaveKeys(['input', 'cookies', 'authorization', 'session']);

                return true;
            });

        $this->getJson('/_testing/request-id')->assertOk();
    });

    it('レスポンスヘッダーに X-Request-Id を返せる', function () {
        $response = $this->getJson('/_testing/request-id');

        $response->assertOk();

        expect($response->headers->get('X-Request-Id'))
            ->toBe($response->json('request_id'))
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });
});

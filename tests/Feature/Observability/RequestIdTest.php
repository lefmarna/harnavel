<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\SensitiveUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

mutates(SensitiveUrl::class);

beforeEach(function () {
    Route::middleware('web')->get('/_testing/request-id', function (Request $request) {
        return response()->json([
            'request_id' => $request->attributes->get('request_id'),
        ]);
    })->name('testing.request-id');

    Route::middleware('web')->get('/_testing/authenticated-request-id', function (Request $request) {
        return response()->json([
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);
    })->name('testing.authenticated-request-id');
});

describe('問い合わせID', function () {
    it('外部から渡された X-Request-Id を採用せず、アプリ側で生成した ID をレスポンスヘッダーに返す', function () {
        $response = $this->withHeader('X-Request-Id', 'client-supplied-id')
            ->getJson('/_testing/request-id');

        $response->assertOk();

        expect($response->json('request_id'))
            ->not->toBe('client-supplied-id')
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
            ->toBe($response->headers->get('X-Request-Id'));
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

    it('ゲストリクエスト開始時に前リクエストの user_id をリセットできる', function () {
        $user = User::factory()->create();

        Log::shareContext(['user_id' => $user->getAuthIdentifier()]);

        $this->getJson('/_testing/request-id')->assertOk();

        expect(Log::sharedContext())
            ->toHaveKey('user_id')
            ->and(Log::sharedContext()['user_id'])->toBeNull();
    });

    it('認証済みリクエストでは解決後の user_id をログコンテキストに共有できる', function () {
        $user = User::factory()->create();

        $guardSessionKey = Auth::guard()->getName();

        Log::spy();

        $this->withSession([$guardSessionKey => $user->getAuthIdentifier()])
            ->getJson('/_testing/authenticated-request-id')
            ->assertOk()
            ->assertJson([
                'user_id' => $user->getAuthIdentifier(),
            ]);

        Log::shouldHaveReceived('shareContext')
            ->withArgs(function (array $context): bool {
                return array_key_exists('request_id', $context)
                    && array_key_exists('user_id', $context)
                    && $context['user_id'] === null;
            })
            ->once();

        Log::shouldHaveReceived('shareContext')
            ->withArgs(function (array $context) use ($user): bool {
                return $context === [
                    'user_id' => $user->getAuthIdentifier(),
                ];
            })
            ->atLeast()
            ->once();
    });

    it('パス中の秘密を伏せてログコンテキストへ共有できる', function (Closure $url, string $expected, string $route) {
        Log::shouldReceive('shareContext')
            ->once()
            ->withArgs(function (array $context) use ($expected, $route): bool {
                expect($context['url'])->toBe(url($expected))
                    ->and($context['route'])->toBe($route);

                return true;
            });

        $this->get($url());
    })->with([
        'パスワード再設定トークン' => [
            fn (): string => route('password.reset', ['token' => 'secret-token', 'email' => 'user@example.com']),
            'reset-password/{token}',
            'password.reset',
        ],
        '秘密を含まないトップページ' => [
            fn (): string => route('home'),
            '/',
            'home',
        ],
        'パーセントエンコードされた再設定トークン' => [
            fn (): string => url('reset-password/%73ecret-token'),
            'reset-password/{token}',
            'password.reset',
        ],
        'メール確認リンクのハッシュ' => [
            fn (): string => URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => '1', 'hash' => 'secret-hash']),
            'email/verify/1/{hash}',
            'verification.verify',
        ],
    ]);
});

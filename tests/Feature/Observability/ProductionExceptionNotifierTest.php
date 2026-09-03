<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Observability\ProductionExceptionNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('observability.discord.webhook_url', 'https://discord.test/webhook');
    config()->set('observability.discord.deduplication_seconds', 300);
    $this->app->instance('env', 'production');

    Cache::flush();
    Http::fake(['discord.test/*' => Http::response('', 204)]);

    Route::middleware('web')->get('/_testing/notify-exception', function () {
        throw new RuntimeException('integration failure');
    });

    Route::middleware('web')->get('/_testing/notify-http-exception', function () {
        abort(503, 'service unavailable');
    });

    Route::middleware('web')->get('/_testing/ignored-http-exception', function () {
        abort(404);
    });
});

function observabilityRequest(): Request
{
    $request = Request::create('https://example.test/orders/123?token=secret-token', 'POST', [
        'password' => 'secret-password',
    ]);

    $route = new RoutingRoute(['POST'], 'orders/{order}', []);
    $route->name('orders.store');

    $request->setRouteResolver(fn (): RoutingRoute => $route);
    $request->headers->set('Authorization', 'Bearer secret-token');
    $request->headers->set('Cookie', 'session=secret-session');
    $request->headers->set('User-Agent', 'Pest Browser');
    $request->attributes->set('request_id', '01JZ0000000000000000000000');

    return $request;
}

function observabilityAppName(): string
{
    $appName = config('app.name');

    return is_string($appName) && $appName !== '' ? $appName : 'Laravel';
}

describe('本番例外通知', function () {
    it('HTTPリクエストの未処理例外を Discord 通知へ接続できる', function () {
        $this->get('/_testing/notify-exception')->assertInternalServerError();

        Http::assertSentCount(1);
    });

    it('HTTPリクエストの500系HTTP例外を Discord 通知へ接続できる', function () {
        $this->get('/_testing/notify-http-exception')->assertServiceUnavailable();

        Http::assertSentCount(1);
    });

    it('HTTPリクエストの500系ではないHTTP例外は Discord 通知しない', function () {
        $this->get('/_testing/ignored-http-exception')->assertNotFound();

        Http::assertNothingSent();
    });

    it('production で Discord Webhook が設定されている場合は未処理例外を通知予約できる', function () {
        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );

        Http::assertNothingSent();

        app()->terminate();

        Http::assertSentCount(1);
    });

    it('Discord Webhook が未設定の場合は通知しない', function () {
        config()->set('observability.discord.webhook_url', null);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
    });

    it('通知対象外の環境では通知しない', function () {
        $this->app->instance('env', 'local');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
    });

    it('develop 環境でも通知できる', function () {
        $this->app->instance('env', 'develop');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $appName = observabilityAppName();

            expect($payload['content'])->toBe("[{$appName}][01JZ0000000000000000000000] Develop exception: RuntimeException")
                ->and($payload['embeds'][0]['title'])->toBe('Develop exception');

            return true;
        });
    });

    it('500系ではないHTTP例外は通知しない', function () {
        app(ProductionExceptionNotifier::class)->report(new NotFoundHttpException, observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
    });

    it('499のHTTP例外は通知しない', function () {
        app(ProductionExceptionNotifier::class)->report(new HttpException(499), observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
    });

    it('同一例外キーは5分以内に再通知しない', function () {
        $exception = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('重複制御秒数が1秒でも同一例外キーは再通知しない', function () {
        config()->set('observability.discord.deduplication_seconds', 1);

        $exception = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('同じ経路とメッセージの例外は発生行が違っても再通知しない', function () {
        $first = new RuntimeException('database is unavailable');
        $second = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        expect($first->getLine())->not->toBe($second->getLine());

        app(ProductionExceptionNotifier::class)->report($first, $request);
        app(ProductionExceptionNotifier::class)->report($second, $request);
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('同じ経路でもメッセージが異なる例外は別の障害として通知できる', function () {
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('database is unavailable'), $request);
        app(ProductionExceptionNotifier::class)->report(new RuntimeException('cache is unavailable'), $request);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('同じメッセージでも例外クラスが異なる場合は別の障害として通知できる', function () {
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('shared failure'), $request);
        app(ProductionExceptionNotifier::class)->report(new LogicException('shared failure'), $request);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('同じメッセージでもリクエストパスが異なる場合は別の障害として通知できる', function () {
        $first = observabilityRequest();
        $second = observabilityRequest();
        $second->server->set('REQUEST_URI', '/invoices/987');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('shared failure'), $first);
        app(ProductionExceptionNotifier::class)->report(new RuntimeException('shared failure'), $second);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('重複制御キャッシュが失敗しても例外処理を失敗させず通知できる', function () {
        Cache::shouldReceive('add')
            ->once()
            ->andThrow(new RuntimeException('cache is unavailable'));

        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );

        app()->terminate();

        Http::assertSentCount(1);
    });

    it('5分を過ぎた同一例外キーは再通知できる', function () {
        $exception = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report($exception, $request);
        Cache::flush();
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('Discord payload に request_id と調査用メタ情報を含められる', function () {
        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );

        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields'] ?? [])->pluck('value', 'name');
            $appName = observabilityAppName();

            expect($payload['content'])->toBe("[{$appName}][01JZ0000000000000000000000] Production exception: RuntimeException")
                ->and($request->url())->toBe('https://discord.test/webhook')
                ->and($payload['embeds'][0]['title'])->toBe('Production exception')
                ->and($payload['embeds'][0]['color'])->toBe(15_141_160)
                ->and($fields->get('app'))->toBe($appName)
                ->and($fields->get('request_id'))->toBe('01JZ0000000000000000000000')
                ->and($fields->get('status'))->toBe('500')
                ->and($fields->get('exception'))->toBe(RuntimeException::class)
                ->and($fields->get('message'))->toBe('database is unavailable')
                ->and($fields->get('method'))->toBe('POST')
                ->and($fields->get('url'))->toBe('https://example.test/orders/123')
                ->and($fields->get('route'))->toBe('orders.store')
                ->and($fields->get('ip'))->toBe('127.0.0.1')
                ->and($fields->get('user_agent'))->toBe('Pest Browser')
                ->and($fields->get('file'))->toMatch('/ProductionExceptionNotifierTest\.php:\d+$/')
                ->and($fields->get('environment'))->toBe('production')
                ->and($payload['embeds'][0]['fields'])->each->toHaveKey('inline', true);

            return true;
        });
    });

    it('request がない例外も通知できる', function () {
        app(ProductionExceptionNotifier::class)->report(new RuntimeException('console failure'));
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $fields = collect($request->data()['embeds'][0]['fields'] ?? [])->pluck('value', 'name');

            expect($fields->get('request_id'))->toBe('unavailable')
                ->and($fields)->not->toHaveKeys(['method', 'url', 'route', 'ip', 'user_agent']);

            return true;
        });
    });

    it('request_id が空文字の場合は unavailable として通知できる', function () {
        $request = observabilityRequest();
        $request->attributes->set('request_id', '');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('missing request id'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields'] ?? [])->pluck('value', 'name');
            $appName = observabilityAppName();

            expect($payload['content'])->toBe("[{$appName}][unavailable] Production exception: RuntimeException")
                ->and($fields->get('request_id'))->toBe('unavailable');

            return true;
        });
    });

    it('Discord payload に入力値 Cookie Authorization セッションを含めない', function () {
        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );

        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $json = json_encode($request->data(), JSON_THROW_ON_ERROR);

            expect($json)
                ->not->toContain('secret-password')
                ->not->toContain('secret-token')
                ->not->toContain('secret-session')
                ->not->toContain('Authorization')
                ->not->toContain('Cookie')
                ->not->toContain('session');

            return true;
        });
    });

    it('実値を含み得るDB例外メッセージをマスクする', function (Throwable $exception, string $secret) {
        app(ProductionExceptionNotifier::class)->report($exception, observabilityRequest());

        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request) use ($secret): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields'] ?? [])->pluck('value', 'name');
            $json = json_encode($payload, JSON_THROW_ON_ERROR);

            expect($fields->get('message'))->toBe('Database exception message omitted.')
                ->and($json)->not->toContain($secret);

            return true;
        });
    })->with([
        'QueryException' => [
            new QueryException(
                'mysql',
                'select * from users where email = ?',
                ['secret@example.test'],
                new PDOException("Duplicate entry 'secret@example.test' for key 'users_email_unique'"),
            ),
            'secret@example.test',
        ],
        'PDOException' => [
            new PDOException("Duplicate entry 'raw-secret-value' for key 'users_email_unique'"),
            'raw-secret-value',
        ],
    ]);

    it('report 中に未解決のユーザーを解決しない', function () {
        $request = observabilityRequest();
        $request->setUserResolver(fn () => throw new RuntimeException('user provider was touched'));

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $fields = collect($request->data()['embeds'][0]['fields'] ?? [])->pluck('value', 'name');

            expect($fields)->not->toHaveKey('user_id');

            return true;
        });
    });

    it('解決済みユーザーの user_id を Discord payload に含められる', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request) use ($user): bool {
            $fields = collect($request->data()['embeds'][0]['fields'] ?? [])->pluck('value', 'name');

            expect($fields->get('user_id'))->toBe((string) $user->getAuthIdentifier());

            return true;
        });
    });

    it('payload 構築に失敗しても例外を漏らさない', function () {
        Log::spy();

        $request = observabilityRequest();
        $request->setRouteResolver(fn () => throw new RuntimeException('route resolver failed'));

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertNothingSent();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Discord exception notification was skipped.'
                    && $context === ['exception_class' => RuntimeException::class];
            })
            ->once();
    });

    it('Discord 送信に失敗しても例外処理を失敗させない', function () {
        Log::spy();

        Http::fake(['discord.test/*' => Http::failedConnection()]);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());

        app()->terminate();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Discord exception notification failed.'
                    && $context === ['exception_class' => ConnectionException::class];
            })
            ->once();
    });
});

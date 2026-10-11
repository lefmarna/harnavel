<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Observability\ProductionExceptionNotifier;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportException;

mutates(ProductionExceptionNotifier::class);

beforeEach(function () {
    config()->set('app.name', 'app');
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

    Route::middleware(['web', 'throttle:1,1'])->get('/_testing/throttled', fn () => 'ok');
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

describe('本番例外通知', function () {
    it('HTTPリクエストの未処理例外を Discord 通知へ接続できる', function () {
        $this->get('/_testing/notify-exception')->assertInternalServerError();

        Http::assertSentCount(1);
    });

    it('HTTPリクエストの500系HTTP例外を Discord 通知へ接続できる', function () {
        $this->get('/_testing/notify-http-exception')->assertServiceUnavailable();

        Http::assertSentCount(1);
    });

    // 総当たりや大量登録の兆候に、ログを見に行かなくても気づけるようにする。
    it('HTTPリクエストが回数制限で拒否されたら Discord 通知へ接続できる', function () {
        $this->get('/_testing/throttled')->assertOk();
        $this->get('/_testing/throttled')->assertTooManyRequests();

        Http::assertSentCount(1);
    });

    // パスの ID を変えるだけで通知を大量に送らせない。
    it('回数制限の拒否は同じルートならパスが違っても再通知しない', function () {
        $throttledRequest = function (string $path): Request {
            $request = Request::create("https://example.test/{$path}");
            $route = new RoutingRoute(['GET'], 'items/{item}', []);
            $request->setRouteResolver(fn (): RoutingRoute => $route);

            return $request;
        };

        app(ProductionExceptionNotifier::class)->report(new ThrottleRequestsException, $throttledRequest('items/1'));
        app(ProductionExceptionNotifier::class)->report(new ThrottleRequestsException, $throttledRequest('items/2'));
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('回数制限の拒否はリクエストやルートが無くても重複を抑えて通知できる', function (bool $withRequest) {
        $request = $withRequest ? Request::create('https://example.test/items/1') : null;

        app(ProductionExceptionNotifier::class)->report(new ThrottleRequestsException, $request);
        app(ProductionExceptionNotifier::class)->report(new ThrottleRequestsException, $request);
        app()->terminate();

        Http::assertSentCount(1);
    })->with([
        'リクエストなし' => [false],
        'ルートなし' => [true],
    ]);

    it('回数制限の拒否は500系でなくても通知する', function () {
        app(ProductionExceptionNotifier::class)->report(new ThrottleRequestsException, observabilityRequest());
        app()->terminate();

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

    it('通知予約後に Webhook URL が未設定になった場合は送信しない', function () {
        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());

        config()->set('observability.discord.webhook_url', null);
        app()->terminate();

        Http::assertNothingSent();
    });

    it('Webhook 未設定なら重複判定もせず通知しない', function () {
        config()->set('observability.discord.webhook_url', null);

        Cache::spy();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
        Cache::shouldNotHaveReceived('add');
    });

    it('Webhook URL が文字列でない場合は通知せず例外も投げない', function () {
        config()->set('observability.discord.webhook_url', 12345);

        Log::spy();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertNothingSent();
        Log::shouldNotHaveReceived('warning');
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

            expect($payload['content'])->toBe('[app][01JZ0000000000000000000000] Develop exception: RuntimeException')
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

    it('重複制御秒数が0なら同一例外キーでも毎回通知する', function () {
        config()->set('observability.discord.deduplication_seconds', 0);

        Cache::spy();

        $exception = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(2);
        Cache::shouldNotHaveReceived('add');
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

    it('同じパスでもルート名が異なれば別キーとして通知する', function () {
        $exception = new RuntimeException('database is unavailable');

        $makeRequest = function (string $routeName): Request {
            $request = Request::create('https://example.test/orders/123', 'POST');
            $route = new RoutingRoute(['POST'], 'orders/{order}', []);
            $route->name($routeName);
            $request->setRouteResolver(fn (): RoutingRoute => $route);

            return $request;
        };

        app(ProductionExceptionNotifier::class)->report($exception, $makeRequest('orders.store'));
        app(ProductionExceptionNotifier::class)->report($exception, $makeRequest('orders.update'));
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('同じパスでもルート名の有無で別キーとして通知する', function () {
        $exception = new RuntimeException('database is unavailable');

        $withRoute = Request::create('https://example.test/orders/123', 'POST');
        $route = new RoutingRoute(['POST'], 'orders/{order}', []);
        $route->name('orders.store');
        $withRoute->setRouteResolver(fn (): RoutingRoute => $route);

        $withoutRoute = Request::create('https://example.test/orders/123', 'POST');

        app(ProductionExceptionNotifier::class)->report($exception, $withRoute);
        app(ProductionExceptionNotifier::class)->report($exception, $withoutRoute);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('名前付きルートが無い同一例外も重複通知を抑制する', function () {
        $exception = new RuntimeException('database is unavailable');

        $request = Request::create('https://example.test/orders/123', 'GET');

        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('リクエストが無い同一例外も重複通知を抑制する', function () {
        $exception = new RuntimeException('boom');

        app(ProductionExceptionNotifier::class)->report($exception);
        app(ProductionExceptionNotifier::class)->report($exception);
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('重複判定キャッシュに環境・例外クラス・メッセージ・パス・ルートを含む正規キーを使う', function () {
        $captured = [];
        Cache::shouldReceive('add')->andReturnUsing(function (string $key, mixed $value, int $seconds) use (&$captured): bool {
            $captured = compact('key', 'value', 'seconds');

            return true;
        });

        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );
        app()->terminate();

        $expectedFingerprint = implode('|', [
            'production',
            RuntimeException::class,
            hash('sha256', 'database is unavailable'),
            'orders/123',
            'orders.store',
        ]);

        expect($captured['key'])->toBe('observability:discord-exception:'.hash('sha256', $expectedFingerprint))
            ->and($captured['value'])->toBeTrue()
            ->and($captured['seconds'])->toBe(300);
    });

    it('重複判定キーの環境は実行時に解決するので環境が変われば再通知する', function () {
        $exception = new RuntimeException('database is unavailable');
        $request = observabilityRequest();

        app(ProductionExceptionNotifier::class)->report($exception, $request);

        $this->app->instance('env', 'develop');

        app(ProductionExceptionNotifier::class)->report($exception, $request);
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
        $this->travel(301)->seconds();
        app(ProductionExceptionNotifier::class)->report($exception, $request);
        app()->terminate();

        Http::assertSentCount(2);
    });

    it('Discord payload に request_id と調査用メタ情報を含められる', function () {
        $exception = new RuntimeException('database is unavailable');

        app(ProductionExceptionNotifier::class)->report($exception, observabilityRequest());

        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request) use ($exception): bool {
            $payload = $request->data();
            $embed = $payload['embeds'][0];
            $fields = collect($embed['fields']);
            $byName = $fields->pluck('value', 'name');

            expect($payload['content'])->toBe('[app][01JZ0000000000000000000000] Production exception: RuntimeException')
                ->and($request->url())->toBe('https://discord.test/webhook')
                ->and($embed['title'])->toBe('Production exception')
                ->and($embed['color'])->toBe(15_141_160)
                ->and($fields->pluck('name')->all())->toBe([
                    'app', 'request_id', 'environment', 'status', 'exception',
                    'method', 'url', 'route', 'ip', 'user_agent', 'file',
                ])
                ->and($byName->get('app'))->toBe('app')
                ->and($byName->get('request_id'))->toBe('01JZ0000000000000000000000')
                ->and($byName->get('environment'))->toBe('production')
                ->and($byName->get('status'))->toBe('500')
                ->and($byName->get('exception'))->toBe(RuntimeException::class)
                ->and($byName->get('method'))->toBe('POST')
                ->and($byName->get('url'))->toBe('https://example.test/orders/123')
                ->and($byName->get('route'))->toBe('orders.store')
                ->and($byName->get('ip'))->toBe('127.0.0.1')
                ->and($byName->get('user_agent'))->toBe('Pest Browser')
                ->and($byName->get('file'))->toBe($exception->getFile().':'.$exception->getLine());

            $fields->each(function (array $field): void {
                expect($field)->toHaveKeys(['name', 'value', 'inline'])
                    ->and($field['inline'])->toBeTrue();
            });

            return true;
        });
    });

    it('リクエストが無くても通知でき、リクエスト依存フィールドは省略する', function () {
        app(ProductionExceptionNotifier::class)->report(new RuntimeException('console failure'));
        app()->terminate();

        Http::assertSentCount(1);

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields']);

            expect($payload['content'])->toBe('[app][unavailable] Production exception: RuntimeException')
                ->and($fields->pluck('name')->all())->toBe([
                    'app', 'request_id', 'environment', 'status', 'exception', 'file',
                ])
                ->and($fields->pluck('value', 'name')->get('request_id'))->toBe('unavailable');

            return true;
        });
    });

    it('パス中の再設定トークンを伏せて url を送れる', function () {
        $request = Request::create('https://example.test/reset-password/secret-token', 'GET');
        $route = (new RoutingRoute(['GET'], 'reset-password/{token}', []))->bind($request);
        $request->setRouteResolver(fn (): RoutingRoute => $route);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $sent): bool {
            $byName = collect($sent->data()['embeds'][0]['fields'])->pluck('value', 'name');

            expect($byName->get('url'))->toBe('https://example.test/reset-password/{token}');

            return true;
        });
    });

    it('ルートが解決できないリクエストは route を省き method と url は残す', function () {
        $request = Request::create('https://example.test/orders/123', 'POST');
        $request->attributes->set('request_id', '01JZ0000000000000000000000');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $sent): bool {
            $fields = collect($sent->data()['embeds'][0]['fields']);
            $byName = $fields->pluck('value', 'name');

            expect($fields->pluck('name')->all())->not->toContain('route')
                ->and($byName->get('method'))->toBe('POST')
                ->and($byName->get('url'))->toBe('https://example.test/orders/123');

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

            expect($payload['content'])->toBe('[app][unavailable] Production exception: RuntimeException')
                ->and($fields->get('request_id'))->toBe('unavailable');

            return true;
        });
    });

    it('フィールド値を省略記号込みで Discord の上限1024文字に収める', function () {
        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('failure'),
            Request::create('https://example.test/'.str_repeat('a', 1100)),
        );
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $url = collect($request->data()['embeds'][0]['fields'])->pluck('value', 'name')->get('url');

            expect(mb_strlen($url))->toBe(1024)
                ->and($url)->toStartWith('https://example.test/aaa')
                ->and($url)->toEndWith('...');

            return true;
        });
    });

    it('User-Agent を200文字で打ち切る', function () {
        $request = observabilityRequest();
        $request->headers->set('User-Agent', str_repeat('u', 250));

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $fields = collect($request->data()['embeds'][0]['fields'])->pluck('value', 'name');

            expect($fields->get('user_agent'))->toBe(str_repeat('u', 200).'...');

            return true;
        });
    });

    // Discord は値が空のフィールドを含む payload を受け付けない。
    it('User-Agent が空ならフィールドを省略する', function () {
        $request = observabilityRequest();
        $request->headers->set('User-Agent', '');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), $request);
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $names = collect($request->data()['embeds'][0]['fields'])->pluck('name')->all();

            expect($names)->not->toContain('user_agent');

            return true;
        });
    });

    it('app.name が空なら content とフィールドのアプリ名に Laravel を使う', function () {
        config()->set('app.name', '');

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields'])->pluck('value', 'name');

            expect($payload['content'])->toStartWith('[Laravel]')
                ->and($fields->get('app'))->toBe('Laravel');

            return true;
        });
    });

    it('app.name が文字列でなければ content とフィールドのアプリ名に Laravel を使う', function () {
        config()->set('app.name', ['array-name']);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $payload = $request->data();
            $fields = collect($payload['embeds'][0]['fields'])->pluck('value', 'name');

            expect($payload['content'])->toStartWith('[Laravel]')
                ->and($fields->get('app'))->toBe('Laravel');

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

    // 例外メッセージには SMTP の応答に含まれる宛先や SQL の値が入りうる。詳細は request_id でログから探す。
    it('例外メッセージを Discord へ送らない', function (Throwable $exception, string $secret) {
        app(ProductionExceptionNotifier::class)->report($exception, observabilityRequest());

        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request) use ($secret): bool {
            $payload = $request->data();
            $names = collect($payload['embeds'][0]['fields'])->pluck('name')->all();

            expect($names)->not->toContain('message')
                ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain($secret);

            return true;
        });
    })->with([
        'SMTP の応答' => [
            new TransportException('Expected response code "250" but got code "550", with message "550 5.1.1 <smtp-secret@example.test> User unknown".'),
            'smtp-secret@example.test',
        ],
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

    // DB の例外は値ごとにメッセージが変わるため、同じ経路の障害としてまとめる。
    it('DB の例外はメッセージが値ごとに違っても同じ経路なら再通知しない', function () {
        $notifier = app(ProductionExceptionNotifier::class);

        $notifier->report(new PDOException("Duplicate entry 'first@example.test' for key 'users_email_unique'"), observabilityRequest());
        $notifier->report(new PDOException("Duplicate entry 'second@example.test' for key 'users_email_unique'"), observabilityRequest());
        app()->terminate();

        Http::assertSentCount(1);
    });

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

    it('未認証時は Auth::user() を解決せずに通知する', function () {
        Auth::shouldReceive('hasUser')->andReturnFalse();
        Auth::shouldReceive('user')->never();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSentCount(1);
    });

    it('認証済み判定でもユーザー実体が無ければ user_id を省く', function () {
        Auth::shouldReceive('hasUser')->andReturnTrue();
        Auth::shouldReceive('user')->andReturnNull();

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $names = collect($request->data()['embeds'][0]['fields'])->pluck('name')->all();

            expect($names)->not->toContain('user_id');

            return true;
        });
    });

    it('整数の user_id も文字列フィールドへ変換して含める', function () {
        $authenticatable = Mockery::mock(Authenticatable::class);
        $authenticatable->shouldReceive('getAuthIdentifier')->andReturn(42);

        Auth::shouldReceive('hasUser')->andReturnTrue();
        Auth::shouldReceive('user')->andReturn($authenticatable);

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        Http::assertSent(function (HttpClientRequest $request): bool {
            $fields = collect($request->data()['embeds'][0]['fields'])->pluck('value', 'name');

            expect($fields->get('user_id'))->toBe('42');

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

    it('payload 構築失敗時にログ出力も失敗しても元の例外処理を妨げない', function () {
        Auth::shouldReceive('hasUser')
            ->once()
            ->andThrow(new RuntimeException('auth is unavailable'));
        Log::shouldReceive('warning')
            ->once()
            ->andThrow(new RuntimeException('log is unavailable'));

        app(ProductionExceptionNotifier::class)->report(
            new RuntimeException('database is unavailable'),
            observabilityRequest(),
        );

        app()->terminate();

        Http::assertNothingSent();
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

    it('Discord 送信に型付き設定値のタイムアウトを適用する', function () {
        config()->set('observability.discord.timeout', 5.0);
        config()->set('observability.discord.connect_timeout', 3.0);

        $captured = [];
        Http::globalMiddleware(function (callable $handler) use (&$captured) {
            return function ($request, array $options) use (&$captured, $handler) {
                $captured[] = $options;

                return $handler($request, $options);
            };
        });

        app(ProductionExceptionNotifier::class)->report(new RuntimeException('failure'), observabilityRequest());
        app()->terminate();

        expect($captured)->toHaveCount(1)
            ->and($captured[0]['timeout'])->toBe(5.0)
            ->and($captured[0]['connect_timeout'])->toBe(3.0);
    });
});

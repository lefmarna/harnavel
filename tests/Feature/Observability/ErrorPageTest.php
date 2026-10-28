<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\ResponseFactory;

beforeEach(function () {
    $this->app->instance('env', 'production');
    config()->set('app.debug', false);
    config()->set('observability.discord.webhook_url', null);

    // GET はバージョン不一致で 409 になるため、Inertia と同じく文字列化して一致させる（ビルド前は null）。
    $this->inertiaHeaders = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];

    Route::middleware('web')->get('/_testing/errors/500', function () {
        throw new RuntimeException('secret stack message');
    });

    Route::middleware('web')->post('/_testing/errors/500-with-input', function () {
        throw new RuntimeException('secret stack message');
    });

    Route::middleware('web')->get('/_testing/errors/503', function () {
        abort(503, 'maintenance secret message');
    });

    Route::middleware('web')->get('/_testing/errors/419', function () {
        abort(419);
    });

    Route::middleware('web')->get('/_testing/errors/403', fn () => abort(403));
    Route::get('/_testing/errors/404-without-session', fn () => abort(404));
    Route::middleware('web')->get('/_testing/errors/404', fn () => abort(404));
    Route::middleware(['web', 'throttle:1,1'])->get('/_testing/errors/429', fn () => 'ok');
    Route::middleware(['web', 'throttle:1,1,testing-form'])->match(['post', 'put'], '/_testing/errors/429-form', fn () => back());
});

describe('本番エラーページ', function () {
    it('エラーページに定型文言と問い合わせIDを表示する', function (string $uri, int $status, string $heading) {
        $response = $this->get($uri);

        $response->assertStatus($status);

        $requestId = $response->headers->get('X-Request-Id');

        expect($requestId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
        expect($response->getContent())
            ->toContain("<title>{$heading} - ")
            ->toContain("<h1>{$heading}</h1>")
            ->toContain('時間をおいてもう一度お試しください。お問い合わせの際は、以下の問い合わせIDをお知らせください。')
            ->toContain((string) $requestId)
            ->toContain('トップページへ戻る');
    })->with([
        '500' => ['/_testing/errors/500', 500, 'エラーが発生しました'],
        '503' => ['/_testing/errors/503', 503, '現在利用できません'],
    ]);

    it('500 は問い合わせIDが無いときプレースホルダを日本語で示す', function () {
        // AssignRequestId を通らない経路では ID が無い。読み手に見える位置なので、
        // 翻訳を通ることを固定する。訳が無ければキー名が出て落ちる。
        expect(view('errors.500')->render())->toContain('問い合わせID: <code>不明</code>');
    });

    it('503 は問い合わせIDが無いとき問い合わせIDを求めない', function () {
        // 事前生成の 503 はリクエストを持たないため ID が常に無い。つまりこれが通常の状態で、
        // 「IDをお知らせください」と促しながら中身を伏せ字にするのは案内として成立しない。
        $html = view('errors.503')->render();

        expect($html)
            ->toContain('時間をおいてもう一度お試しください。')
            ->not->toContain('問い合わせID');
    });

    it('本番エラーページにスタックトレースや入力値を表示しない', function () {
        // Inertia レスポンスはページオブジェクトの url にクエリ文字列を含むため、
        // 派生アプリでも誤検知しないよう入力値はリクエストボディで送信して検証する
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->post('/_testing/errors/500-with-input', ['password' => 'secret-password']);

        $response->assertInternalServerError();

        expect($response->getContent())
            ->not->toContain('secret stack message')
            ->not->toContain('secret-password')
            ->not->toContain('Stack trace');
    });
});

describe('SPA遷移時の500エラーページ', function () {
    it('SPA遷移時はInertiaの500画面に問い合わせIDを渡す', function () {
        $response = $this->get('/_testing/errors/500', $this->inertiaHeaders);

        $requestId = $response->headers->get('X-Request-Id');

        $response->assertInternalServerError();
        $response->assertJsonPath('component', 'errors/server-error');
        expect($requestId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
        $response->assertJsonPath('props.requestId', $requestId);
    });

    it('SPA遷移時にスタックトレースや入力値を表示しない', function () {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->post(
            '/_testing/errors/500-with-input',
            ['password' => 'secret-password'],
            $this->inertiaHeaders,
        );

        $response->assertInternalServerError();
        $response->assertJsonPath('component', 'errors/server-error');
        expect($response->getContent())
            ->not->toContain('secret stack message')
            ->not->toContain('secret-password')
            ->not->toContain('Stack trace');
    });

    it('SPA遷移時の419は直前のページへ有効期限切れのエラーを返す', function () {
        $response = $this
            ->from('/_testing/errors/500')
            ->get('/_testing/errors/419', $this->inertiaHeaders);

        $response->assertRedirect('/_testing/errors/500');
        $response->assertSessionHasErrors([
            'page_expired' => 'ページの有効期限が切れました。再度お試しください。',
        ]);
    });

    it('デバッグ時はSPA遷移でも変換せず例外詳細を保持する', function () {
        config()->set('app.debug', true);

        $response = $this->get('/_testing/errors/500', $this->inertiaHeaders);

        $response->assertInternalServerError();
        // 汎用エラーページへ差し替えず、Ignition のデバッグレスポンスを素通しする。
        // 変換後の汎用ページは例外メッセージを一切含まないため、メッセージの存在で
        // 「変換されていない＝調査用の詳細が残る」ことを担保できる。
        expect($response->getContent())->toContain('secret stack message');
    });
});

describe('403・404・429 のエラーページ', function () {
    $request = function (string $uri, array $headers = []) {
        if ($uri === '/_testing/errors/429') {
            test()->get($uri);
            // 前の要求で共有したデータを、次の要求へ持ち越さない。
            app()->forgetInstance(ResponseFactory::class);
            Inertia::clearResolvedInstance(ResponseFactory::class);
        }

        return test()->get($uri, $headers);
    };

    it('アプリの画面を、検索結果に出さない設定で返す', function (string $uri, int $status, string $component, string $title) use ($request) {
        $request($uri)
            ->assertStatus($status)
            ->assertInertia(fn ($page) => $page
                ->component($component)
                ->where('seo.title', $title)
                ->where('seo.robots', 'noindex, nofollow'));
    })->with([
        '403' => ['/_testing/errors/403', 403, 'errors/forbidden', 'アクセスできません'],
        '404' => ['/_testing/errors/404', 404, 'errors/not-found', 'ページが見つかりません'],
        '存在しない URL' => ['/_testing/no-such-page', 404, 'errors/not-found', 'ページが見つかりません'],
        '429' => ['/_testing/errors/429', 429, 'errors/too-many-requests', 'しばらくお待ちください'],
    ]);

    // 画面の共通部分は、共有データの url や currentUrl を前提にする。
    it('SPA遷移時もアプリの画面を、共有データとともに返す', function (string $uri, int $status, string $component) use ($request) {
        $request($uri, $this->inertiaHeaders)
            ->assertStatus($status)
            ->assertJsonPath('component', $component)
            ->assertJsonPath('props.currentUrl', url($uri));
    })->with([
        '403' => ['/_testing/errors/403', 403, 'errors/forbidden'],
        '404' => ['/_testing/no-such-page', 404, 'errors/not-found'],
        '429' => ['/_testing/errors/429', 429, 'errors/too-many-requests'],
    ]);

    it('429 でも、古い版の画面からの SPA 遷移は読み直させる', function () use ($request) {
        $request('/_testing/errors/429', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/_testing/errors/429'));
    });

    it('429 の画面にも今の版を付け、次の遷移で読み直させない', function () use ($request) {
        app()->bind(HandleInertiaRequests::class, fn () => new class extends HandleInertiaRequests
        {
            public function version(Request $request): ?string
            {
                return 'current';
            }
        });

        $request('/_testing/errors/429', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'current'])
            ->assertStatus(429)
            ->assertJsonPath('version', 'current');
    });

    it('セッションのない要求では、既定の応答のまま返す', function () {
        $this->get('/_testing/errors/404-without-session')->assertNotFound();
    });

    it('JSON を求める要求には画面を返さない', function () {
        $this->getJson('/_testing/errors/404')
            ->assertNotFound()
            ->assertJsonMissingPath('component');
    });

    it('429 は再試行までの秒数を残す', function () use ($request) {
        expect($request('/_testing/errors/429')->headers->get('Retry-After'))->not->toBeNull();
    });

    it('SPA のフォーム送信が回数制限にかかったときは、入力を残せるよう直前の画面へエラーを返す', function () {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->post('/_testing/errors/429-form');

        $this->from(route('home'))
            ->post('/_testing/errors/429-form', [], $this->inertiaHeaders)
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['too_many_requests' => pageCopy('pages.errors.too_many_requests.description')]);
    });

    // 回数制限は HandleInertiaRequests より先に拒否するので、PUT を GET で戻らせる変換は全体のミドルウェアに頼る。
    it('SPA の PUT の送信が回数制限にかかっても、戻り先へ PUT を送り直させない', function () {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->put('/_testing/errors/429-form');

        $this->from(route('home'))
            ->put('/_testing/errors/429-form', [], $this->inertiaHeaders)
            ->assertStatus(303)
            ->assertRedirect(route('home'));
    });
});

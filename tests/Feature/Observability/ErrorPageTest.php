<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

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

    it('500 エラーページに定型文言を表示する', function () {
        $response = $this->get('/_testing/errors/500');

        expect($response->getContent())
            ->toContain('<title>エラーが発生しました - ')
            ->toContain('<h1>エラーが発生しました</h1>')
            ->toContain('時間をおいてもう一度お試しください。お問い合わせの際は、以下の問い合わせIDをお知らせください。')
            ->toContain('トップページへ戻る');
    });

    it('503 エラーページに定型文言を表示する', function () {
        $response = $this->get('/_testing/errors/503');

        expect($response->getContent())
            ->toContain('<title>現在利用できません - ')
            ->toContain('<h1>現在利用できません</h1>')
            ->toContain('時間をおいてもう一度お試しください。お問い合わせの際は、以下の問い合わせIDをお知らせください。')
            ->toContain('トップページへ戻る');
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

    it('フルページロード時はBladeの500ページを返す', function () {
        $response = $this->get('/_testing/errors/500');

        $response->assertInternalServerError();
        $response->assertHeaderMissing('X-Inertia');
        expect($response->getContent())->toContain('<!DOCTYPE html>');
    });
});

describe('SPA遷移時の500エラーページ', function () {
    // SPA 遷移（X-Inertia あり）では Inertia は HTML ではなく JSON を返すため、
    // assertInertia（ビューデータ前提）ではなく JSON ペイロードを直接検証する。
    it('SPA遷移時にInertiaコンポーネントを返す', function () {
        $response = $this->get('/_testing/errors/500', $this->inertiaHeaders);

        $response->assertInternalServerError();
        $response->assertJsonPath('component', 'errors/server-error');
    });

    it('SPA遷移時にrequestId propを返す', function () {
        $response = $this->get('/_testing/errors/500', $this->inertiaHeaders);

        $requestId = $response->headers->get('X-Request-Id');

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

    it('SPA遷移時のレスポンスがステータスコード500を返す', function () {
        $response = $this->get('/_testing/errors/500', $this->inertiaHeaders);

        $response->assertInternalServerError();
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

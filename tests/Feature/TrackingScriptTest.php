<?php

declare(strict_types=1);

use App\Support\SensitiveUrl;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

mutates(SensitiveUrl::class);

describe('トラッキングスクリプト', function () {
    it('環境に応じてトラッキングスクリプトの注入を切り替える', function (string $env, bool $enabled, bool $adsTestMode) {
        $this->app->instance('env', $env);

        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

        $response = $this->get(route('home'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracking.trackingEnabled', $enabled)
            ->where('tracking.adsEnabled', $enabled)
            ->where('tracking.adsensePublisherId', 'ca-pub-1234567890123456')
            ->where('tracking.adsTestMode', $adsTestMode)
        );

        foreach ([
            'https://www.googletagmanager.com/gtag/js',
            'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
        ] as $script) {
            $enabled
                ? expect($content)->toContain($script)
                : expect($content)->not->toContain($script);
        }
    })->with([
        '本番環境では注入する' => ['production', true, false],
        'develop環境ではテストモードで注入する' => ['develop', true, true],
        'production/develop以外では注入しない' => ['testing', false, false],
    ]);

    it('秘密を含むURLの画面ではトラッキングスクリプトを注入しない', function (Closure $url) {
        $this->app->instance('env', 'production');

        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

        $response = $this->get($url());
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracking.trackingEnabled', false)
            ->where('tracking.adsEnabled', false)
        );

        expect($content)
            ->not->toContain('https://www.googletagmanager.com/gtag/js')
            ->not->toContain('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
    })->with([
        'パスワード再設定画面' => [fn (): string => route('password.reset', ['token' => 'secret-token', 'email' => 'user@example.com'])],
        '署名付きのGoogle連携確認画面' => [fn (): string => URL::temporarySignedRoute('auth.google.link.verify', now()->addHour(), ['userId' => '1', 'payload' => 'secret-payload'])],
    ]);

    // 回数制限で画面を返すと、URL に残る認可コードと state を GA へ渡してしまう。
    it('Google ログインのコールバックの画面ではトラッキングスクリプトを注入しない', function () {
        $this->app->instance('env', 'production');
        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        $url = route('auth.google.callback', ['code' => 'secret-code', 'state' => 'secret-state']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get($url);
        }

        $this->get($url)
            ->assertTooManyRequests()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tracking.trackingEnabled', false));
    });

    it('秘密を含むURLの画面から通常画面へ移るときは全体を読み込み直させる', function () {
        $this->app->instance('env', 'production');
        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');

        $secretVersion = $this->get(route('password.reset', ['token' => 'secret-token']))->viewData('page')['version'];
        $normalVersion = $this->get(route('home'))->viewData('page')['version'];

        expect($secretVersion)->not->toBe($normalVersion);

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $secretVersion])
            ->get(route('home'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));
    });

    it('トラッキングを使わない環境では秘密を含むURLの画面でも読み込み直させない', function () {
        $secretVersion = $this->get(route('password.reset', ['token' => 'secret-token']))->viewData('page')['version'];

        expect($secretVersion)->toBe($this->get(route('home'))->viewData('page')['version']);
    });
});

<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

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
});

<?php

use Inertia\Testing\AssertableInertia;

describe('トラッキング同意', function () {
    it('本番環境では同意Cookieがなくてもトラッキングスクリプトを注入する', function () {
        $this->app->instance('env', 'production');

        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

        $response = $this->get(route('home'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracking.trackingEnabled', true)
            ->where('tracking.adsEnabled', true)
            ->where('tracking.adsensePublisherId', 'ca-pub-1234567890123456')
            ->where('tracking.adsTestMode', false)
        );

        expect($content)->toContain('https://www.googletagmanager.com/gtag/js')
            ->and($content)->toContain('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
    });

    it('develop環境では同意Cookieがなくてもトラッキングスクリプトを注入する', function () {
        $this->app->instance('env', 'develop');

        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

        $response = $this->get(route('home'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracking.trackingEnabled', true)
            ->where('tracking.adsEnabled', true)
            ->where('tracking.adsensePublisherId', 'ca-pub-1234567890123456')
            ->where('tracking.adsTestMode', true)
        );

        expect($content)->toContain('https://www.googletagmanager.com/gtag/js')
            ->and($content)->toContain('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
    });

    it('production/develop以外ではトラッキングスクリプトを注入しない', function () {
        config()->set('services.google.analytics_tracking_id', 'G-TEST123456');
        config()->set('services.google.adsense_publisher_id', 'ca-pub-1234567890123456');

        $response = $this->get(route('home'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracking.trackingEnabled', false)
            ->where('tracking.adsEnabled', false)
            ->where('tracking.adsensePublisherId', 'ca-pub-1234567890123456')
            ->where('tracking.adsTestMode', false)
        );

        expect($content)->not->toContain('https://www.googletagmanager.com/gtag/js')
            ->and($content)->not->toContain('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
    });
});

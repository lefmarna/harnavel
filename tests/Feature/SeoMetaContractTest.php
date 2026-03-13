<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

test('welcomeページはtitleとdescriptionを含むseo propsを返す', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('welcome')
        ->has('seo')
        ->where('seo.title', 'Welcome')
        ->has('seo.description')
    );
});

test('welcomeページの初期HTMLにはInertia管理のSEOタグが含まれる', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    expect($content)->toContain('<link rel="canonical" inertia="canonical"')
        ->and($content)->toContain('<title inertia>Welcome - ')
        ->and($content)->toContain('<meta inertia="description" name="description"')
        ->and($content)->toContain('<meta inertia="robots" name="robots"')
        ->and($content)->toContain('<meta inertia="og:type" property="og:type" content="website">')
        ->and($content)->toContain('<meta inertia="og:url" property="og:url"')
        ->and($content)->toContain('<meta inertia="og:site_name" property="og:site_name"')
        ->and($content)->toContain('<meta inertia="og:title" property="og:title"')
        ->and($content)->toContain('<meta inertia="og:description" property="og:description"')
        ->and($content)->toContain('<meta inertia="og:image" property="og:image"')
        ->and($content)->toContain('<meta inertia="twitter:card" name="twitter:card" content="summary_large_image">')
        ->and($content)->toContain('<meta inertia="twitter:url" name="twitter:url"')
        ->and($content)->toContain('<meta name="twitter:site" content="@lefmarna">')
        ->and($content)->toContain('<meta name="twitter:creator" content="@lefmarna">')
        ->and($content)->toContain('<meta inertia="twitter:title" name="twitter:title"')
        ->and($content)->toContain('<meta inertia="twitter:description" name="twitter:description"')
        ->and($content)->toContain('<meta inertia="twitter:image" name="twitter:image"');

    preg_match('/property="og:image"\s+content="([^"]*)"/', $content, $ogImageMatches);
    preg_match('/name="twitter:image"\s+content="([^"]*)"/', $content, $twitterImageMatches);

    expect($ogImageMatches[1] ?? '')->toEndWith('/opengraph-image.png')
        ->and($twitterImageMatches[1] ?? '')->toEndWith('/opengraph-image.png');
});

test('テスト環境のレスポンスにはdebugbarアセットが混入しない', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    expect($content)->not->toContain('id="phpdebugbar"')
        ->and($content)->not->toContain('phpdebugbar.addDataSet')
        ->and($content)->not->toContain('/_debugbar/');
});

test('機微なルートのog:urlはクエリ文字列を含まない', function () {
    $url = route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']);
    $response = $this->get($url);
    $content = $response->getContent();

    preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
    $ogUrl = $matches[1] ?? '';

    expect($ogUrl)->not->toBeEmpty()
        ->and($ogUrl)->not->toContain('email=')
        ->and($ogUrl)->not->toContain('?')
        ->and($ogUrl)->toContain('reset-password');
});

test('og:urlはクエリ文字列を含まないcanonical pathを持つ', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
    $ogUrl = $matches[1] ?? '';

    expect($ogUrl)->not->toBeEmpty()
        ->and($ogUrl)->not->toContain('?');
});

test('shared propsにはappDisplayName・appDescription・seo・urls・flashが含まれる', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('url', rtrim(url('/'), '/'))
        ->where('currentUrl', route('home'))
        ->has('appDisplayName')
        ->has('appDescription')
        ->has('seo')
        ->has('flash')
        ->where('flash.status', null)
        ->where('flash.error', null)
    );
});

test('shared auth userは安定した任意フィールドを公開する', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('auth.user')
        ->where('auth.user.avatar', null)
        ->where('auth.user.two_factor_enabled', false)
        ->where('auth.hasPassword', true)
    );
});

test('shared auth userは二段階認証の有効状態を返す', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('auth.user')
        ->where('auth.user.two_factor_enabled', true)
    );
});

test('guest authページはnoindex robots metaとseoを使う', function (string $routeName, array $routeParams = []) {
    $response = $this->get(route($routeName, $routeParams));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.robots', 'noindex, nofollow')
    );

    expect($response->getContent())->toContain('<meta inertia="robots" name="robots" content="noindex, nofollow">');
})->with([
    'login' => ['login'],
    'register' => ['register'],
    'forgot-password' => ['password.request'],
    'reset-password' => ['password.reset', ['token' => 'test-token']],
]);

test('publicページはindexableなrobots metaを維持する', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    expect($content)->toContain('<meta inertia="robots" name="robots" content="index, follow, max-video-preview:-1, max-image-preview:large, max-snippet:-1">');
});

test('各ページはseo.titleを公開する', function (string $routeName, string $expectedTitle, array $routeParams = []) {
    $response = $this->get(route($routeName, $routeParams));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', $expectedTitle)
    );
})->with([
    'welcome' => ['home', 'Welcome'],
    'login' => ['login', 'Log in'],
    'register' => ['register', 'Register'],
    'forgot-password' => ['password.request', 'Forgot password'],
    'reset-password' => ['password.reset', 'Reset password', ['token' => 'test-token']],
]);

test('認証済みページはseo.titleを持つ', function (string $routeName, string $expectedTitle) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', $expectedTitle)
    );
})->with([
    'dashboard' => ['dashboard', 'Dashboard'],
    'profile' => ['profile.edit', 'Profile settings'],
    'password' => ['user-password.edit', 'Password settings'],
    'appearance' => ['appearance.edit', 'Appearance settings'],
]);

test('二段階認証設定ページはseo.titleを持つ', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Two-Factor Authentication')
    );
});

test('メール認証ページはseo.titleを持つ', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $response = $this->actingAs($user)->get(route('verification.notice'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Email verification')
        ->where('seo.robots', 'noindex, nofollow')
    );

    expect($content)->toContain('<meta inertia="robots" name="robots" content="noindex, nofollow">');
});

test('パスワード確認ページはseo.titleを持つ', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Confirm password')
        ->where('seo.robots', 'noindex, nofollow')
    );

    expect($content)->toContain('<meta inertia="robots" name="robots" content="noindex, nofollow">');
});

test('二段階認証チャレンジページはseo.titleを持つ', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->get(route('two-factor.login'));
    $content = $response->getContent();

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Two-Factor Authentication')
        ->where('seo.robots', 'noindex, nofollow')
    );

    expect($content)->toContain('<meta inertia="robots" name="robots" content="noindex, nofollow">');
});

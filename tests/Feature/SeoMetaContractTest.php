<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

test('welcome page has seo props with title and description', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('welcome')
        ->has('seo')
        ->where('seo.title', 'Welcome')
        ->has('seo.description')
    );
});

test('welcome page initial HTML contains inertia-managed seo tags', function () {
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
});

test('og:url excludes query string for sensitive routes', function () {
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

test('og:url contains canonical path without query string', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
    $ogUrl = $matches[1] ?? '';

    expect($ogUrl)->not->toBeEmpty()
        ->and($ogUrl)->not->toContain('?');
});

test('shared props include appDisplayName, appDescription, seo, urls, and flash', function () {
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

test('shared auth user exposes stable optional fields', function () {
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

test('shared auth user reports enabled two factor state', function () {
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

test('guest auth pages use noindex robots meta and seo', function (string $routeName, array $routeParams = []) {
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

test('public pages keep indexable robots meta', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    expect($content)->toContain('<meta inertia="robots" name="robots" content="index, follow, max-video-preview:-1, max-image-preview:large, max-snippet:-1">');
});

test('pages expose seo.title', function (string $routeName, string $expectedTitle, array $routeParams = []) {
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

test('authenticated pages have seo.title', function (string $routeName, string $expectedTitle) {
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

test('two-factor settings page has seo.title', function () {
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

test('verify-email page has seo.title', function () {
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

test('confirm-password page has seo.title', function () {
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

test('two-factor-challenge page has seo.title', function () {
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

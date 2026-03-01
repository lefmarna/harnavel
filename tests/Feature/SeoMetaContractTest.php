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

test('welcome page initial HTML contains correct meta tags', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    expect($content)->toContain('<title inertia>Welcome - ')
        ->and($content)->toContain('property="og:title"')
        ->and($content)->toContain('name="description"');
});

test('og:url excludes query string for sensitive routes', function () {
    $url = route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']);
    $response = $this->get($url);
    $content = $response->getContent();

    // Extract og:url value from meta tag
    preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
    $ogUrl = $matches[1] ?? '';

    // Query string params (email) should be excluded; path params (token) are part of the canonical URL
    expect($ogUrl)->not->toBeEmpty()
        ->and($ogUrl)->not->toContain('email=')
        ->and($ogUrl)->not->toContain('?')
        ->and($ogUrl)->toContain('reset-password');
});

test('og:url contains canonical path without query string', function () {
    $response = $this->get(route('home'));
    $content = $response->getContent();

    // Extract og:url value from meta tag
    preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
    $ogUrl = $matches[1] ?? '';

    expect($ogUrl)->not->toBeEmpty()
        ->and($ogUrl)->not->toContain('?');
});

test('shared props include appDisplayName and appDescription', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('appDisplayName')
        ->has('appDescription')
    );
});

// Guest pages
test('guest pages have seo.title', function (string $routeName, string $expectedTitle, array $routeParams = []) {
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

// Auth-required pages
test('auth pages have seo.title', function (string $routeName, string $expectedTitle) {
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

// Auth pages requiring specific state
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

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Email verification')
    );
});

test('confirm-password page has seo.title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Confirm password')
    );
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

    // Simulate the two-factor challenge session state
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->get(route('two-factor.login'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('seo')
        ->where('seo.title', 'Two-Factor Authentication')
    );
});

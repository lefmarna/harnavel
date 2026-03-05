<?php

use App\Exceptions\GoogleAuthException;
use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\mock;

test('redirect() でゲストを Google OAuth にリダイレクトする', function () {
    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get(route('auth.google'))
        ->assertRedirect('https://accounts.google.com/auth');
});

test('redirect() は Google OAuth 未設定時にログイン画面へ戻す', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('auth.google'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Googleログインは現在利用できません。');
});

test('callback() は Google OAuth 未設定時にログイン画面へ戻す', function () {
    config(['services.google.client_secret' => null]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Googleログインは現在利用できません。');
});

test('login view は Google OAuth 設定不足時に canGoogleLogin=false を返す', function () {
    config(['services.google.redirect' => null]);

    $this->get(route('login'))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('canGoogleLogin', false));
});

test('login view は Google OAuth 設定完備時に canGoogleLogin=true を返す', function () {
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret', 'services.google.redirect' => 'https://example.com/callback']);

    $this->get(route('login'))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('canGoogleLogin', true));
});

test('callback() で二段階認証が無効ならそのままログイン完了して fortify.home へリダイレクトする', function () {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->with('google-123', 'test@example.com', 'Test User')
        ->andReturn($existingUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(config('fortify.home'))
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember');

    $this->assertAuthenticatedAs($existingUser);
});

test('callback() は intended URL があれば fortify.home より優先してリダイレクトする', function () {
    $existingUser = User::factory()->create(['email' => 'test@example.com']);

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->with('google-123', 'test@example.com', 'Test User')
        ->andReturn($existingUser);

    $intended = route('profile.edit');

    $this->withSession(['url.intended' => $intended])
        ->get(route('auth.google.callback'))
        ->assertRedirect($intended);

    $this->assertAuthenticatedAs($existingUser);
});

test('callback() で二段階認証が有効ならチャレンジ画面へリダイレクトする', function () {
    Event::fake([TwoFactorAuthenticationChallenged::class]);

    $existingUser = User::factory()->create([
        'email' => 'test@example.com',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->with('google-123', 'test@example.com', 'Test User')
        ->andReturn($existingUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $existingUser->getKey())
        ->assertSessionHas('login.remember', false);

    $this->assertGuest();

    Event::assertDispatched(TwoFactorAuthenticationChallenged::class);
});

test('callback() で二段階認証 feature が無効なら二段階認証設定が残っていてもそのままログイン完了する', function () {
    Event::fake([TwoFactorAuthenticationChallenged::class]);

    config([
        'fortify.features' => array_values(array_filter(
            config('fortify.features', []),
            fn (string $feature): bool => $feature !== \Laravel\Fortify\Features::twoFactorAuthentication(),
        )),
    ]);

    $existingUser = User::factory()->create([
        'email' => 'feature-disabled@example.com',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-feature-disabled');
    $googleUser->allows('getEmail')->andReturn('feature-disabled@example.com');
    $googleUser->allows('getName')->andReturn('Feature Disabled User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->with('google-feature-disabled', 'feature-disabled@example.com', 'Feature Disabled User')
        ->andReturn($existingUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(config('fortify.home'))
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember');

    $this->assertAuthenticatedAs($existingUser);

    Event::assertNotDispatched(TwoFactorAuthenticationChallenged::class);
});

test('callback() で Socialite が例外を投げたらログイン画面にリダイレクトする', function () {
    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andThrow(new \Exception('OAuth error'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

test('callback() でメール未検証なら警告でログイン画面にリダイレクトする', function () {
    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('unverified@example.com');
    $googleUser->allows('getName')->andReturn('Unverified User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => false]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

test('callback() で QueryException が発生してもセッションエラーに SQL が露出しない', function () {
    $secretSql = 'INSERT INTO `users` (`email`, `secret_data`) VALUES (?, ?)';

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->andThrow(new QueryException('mysql', $secretSql, [], new \Exception));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。');

    expect(session('error'))->not->toContain($secretSql);
});

test('callback() で GoogleAuthException のビジネスメッセージがセッションに設定される', function () {
    $errorMessage = 'このメールアドレスは別のGoogleアカウントに連携済みです。';

    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->andThrow(new GoogleAuthException($errorMessage));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', $errorMessage);
});

test('callback() で想定外例外が発生しても安全なメッセージでログイン画面に戻す', function () {
    $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $googleUser->allows('getId')->andReturn('google-123');
    $googleUser->allows('getEmail')->andReturn('test@example.com');
    $googleUser->allows('getName')->andReturn('Test User');
    $googleUser->allows('getRaw')->andReturn(['email_verified' => true]);

    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    mock(GoogleAuthServiceContract::class)
        ->shouldReceive('findOrCreate')
        ->andThrow(new \LogicException('unexpected failure'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。');
});

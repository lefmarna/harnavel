<?php

use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\mock;

test('redirect() でゲストを Google OAuth にリダイレクトする', function () {
    $driver = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
    $driver->allows('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get(route('auth.google'))
        ->assertRedirect('https://accounts.google.com/auth');
});

test('callback() で正常ログインしてホームへリダイレクトする', function () {
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
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($existingUser);
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

test('callback() で RuntimeException のビジネスメッセージがセッションに設定される', function () {
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
        ->andThrow(new \RuntimeException($errorMessage));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', $errorMessage);
});

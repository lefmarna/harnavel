<?php

declare(strict_types=1);

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

use function Pest\Laravel\mock;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => 'https://example.com/auth/google/callback',
    ]);
});

/**
 * Socialite が返す Google ユーザーを差し替える。渡したメソッドだけを許可するので、
 * 呼ばれる前に処理が打ち切られるべきメソッドは省く。
 *
 * @param  array<string, mixed>  $returns
 */
function fakeGoogleUser(array $returns): void
{
    $googleUser = Mockery::mock(Laravel\Socialite\Two\User::class);
    foreach ($returns as $method => $value) {
        $googleUser->allows($method)->andReturn($value);
    }

    $driver = Mockery::mock(GoogleProvider::class);
    $driver->allows('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

describe('Google OAuth コントローラ', function () {
    it('redirect() でゲストを Google OAuth にリダイレクトする', function () {
        $driver = Mockery::mock(GoogleProvider::class);
        $driver->allows('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('auth.google'))
            ->assertRedirect('https://accounts.google.com/auth');
    });

    it('redirect() は Google OAuth 未設定時にログイン画面へ戻す', function () {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Googleログインは現在利用できません。');
    });

    it('callback() は Google OAuth 未設定時にログイン画面へ戻す', function () {
        config(['services.google.client_secret' => null]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Googleログインは現在利用できません。');
    });

    it('callback() は google id が空の場合にログインせずログイン画面へ戻す', function (?string $googleId) {
        $existingUser = User::factory()->create(['google_id' => null]);

        fakeGoogleUser([
            'getId' => $googleId,
            'getEmail' => 'any@example.com',
            'getName' => 'Any User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldNotReceive('findOrCreate');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Googleアカウント情報の取得に失敗しました。もう一度お試しください。');

        $this->assertGuest();
        expect($existingUser->fresh()->google_id)->toBeNull();
    })->with([
        'null' => [null],
        '空文字' => [''],
    ]);

    it('callback() はメールアドレスが取得できない場合にログインせずログイン画面へ戻す', function () {
        fakeGoogleUser([
            'getEmail' => null,
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldNotReceive('findOrCreate');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Googleアカウントのメールアドレスが取得できませんでした。別のアカウントでお試しください。');

        $this->assertGuest();
    });

    it('login view は Google OAuth の設定がそろっているときだけ canGoogleLogin=true を返す', function (?string $redirect, bool $expected) {
        config(['services.google.redirect' => $redirect]);

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/login')
                ->where('canGoogleLogin', $expected));
    })->with([
        '設定不足' => [null, false],
        '設定完備' => ['https://example.com/callback', true],
    ]);

    it('callback() でログイン状態を保持してログインし fortify.home へリダイレクトする', function () {
        $existingUser = User::factory()->create(['email' => 'test@example.com']);

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->with('google-123', 'test@example.com', 'Test User')
            ->andReturn($existingUser);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(config('fortify.home'))
            ->assertCookie(Auth::guard()->getRecallerName());

        $this->assertAuthenticatedAs($existingUser);
    });

    it('パスワード未設定のユーザーも、Google ログインで得たログイン保持 Cookie で復帰できる', function () {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => $user->email,
            'getName' => $user->name,
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andReturn($user);

        $recaller = $this->get(route('auth.google.callback'))
            ->getCookie(Auth::guard()->getRecallerName())
            ->getValue();

        forgetRequestState();
        $this->withCookie(Auth::guard()->getRecallerName(), $recaller)
            ->get(route('dashboard'))
            ->assertOk();
    });

    it('callback() は intended URL があれば fortify.home より優先してリダイレクトする', function () {
        $existingUser = User::factory()->create(['email' => 'test@example.com']);

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

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

    it('callback() で Socialite が例外を投げたらログイン画面にリダイレクトする', function () {
        $driver = Mockery::mock(GoogleProvider::class);
        $driver->allows('user')->andThrow(new Exception('OAuth error'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    });

    it('callback() でメール未検証なら警告でログイン画面にリダイレクトする', function () {
        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'unverified@example.com',
            'getName' => 'Unverified User',
            'getRaw' => ['email_verified' => false],
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Googleアカウントのメールアドレスが未検証です。Googleでメール認証を完了してから再度お試しください。');
    });

    it('callback() で予期しない例外が起きても汎用の文言で戻し、ログには例外クラス名だけを残す', function (Throwable $exception, string $logMessage) {
        Log::spy();

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'leaked@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow($exception);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === $logMessage
                && $context === ['exception_class' => $exception::class])
            ->once();
    })->with([
        'DB の例外' => [fn (): QueryException => new QueryException(
            'mysql',
            'insert into `users` (`email`, `google_id`) values (?, ?)',
            ['leaked@example.com', 'google-123'],
            new Exception('SQLSTATE[23000]: Integrity constraint violation'),
        ), 'Google OAuth database error.'],
        '想定外の例外' => [fn (): LogicException => new LogicException('unexpected failure'), 'Google OAuth unexpected error.'],
    ]);

    it('callback() で GoogleAuthException のビジネスメッセージがセッションに設定される', function () {
        $errorMessage = 'このメールアドレスは別のGoogleアカウントに連携済みです。';

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new GoogleAuthException($errorMessage));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', $errorMessage);
    });

    it('callback() で既存メールアカウント確認待ちなら確認メール送信画面へリダイレクトする', function () {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'existing@example.com',
            'getName' => 'Existing User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new GoogleLinkConfirmationPendingException($existingUser, 'google-123'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('auth.google.link.confirm'))
            ->assertSessionHas('google_link_email_sent', true);
    });

    it('Google 連携確認画面は確認メール送信直後のフローでのみ表示できる', function () {
        $this->withSession(['google_link_email_sent' => true])
            ->get(route('auth.google.link.confirm'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/google-link/confirm')
                ->where('seo.title', 'Googleログイン連携の確認')
                ->where('seo.description', '確認メールからGoogleログイン連携を完了してください。'));
    });

    it('Google 連携確認画面は直接アクセスすると404を返す', function () {
        $this->get(route('auth.google.link.confirm'))
            ->assertNotFound();
    });

    it('callback() で Socialite が例外を投げてもログには例外クラス名だけを残す', function () {
        Log::spy();

        $driver = Mockery::mock(GoogleProvider::class);
        $driver->allows('user')->andThrow(new Exception('OAuth error'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('auth.google.callback'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google OAuth callback failed.'
                    && $context === ['exception_class' => Exception::class];
            })
            ->once();
    });

    it('shared auth data は google_id と認証機密情報をフロントへ公開しない', function () {
        $user = User::factory()->create([
            'google_id' => 'secret-google-id-12345',
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->has('auth.user')
                ->missing('auth.user.google_id')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->where('auth.hasPassword', true)
            );
    });

    describe('Google での本人確認', function () {
        it('Google 連携済みのユーザーを Google のアカウント選択へリダイレクトする', function () {
            $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
            $driver = Mockery::mock(GoogleProvider::class);
            $driver->expects('with')->with(['prompt' => 'select_account'])->andReturnSelf();
            $driver->allows('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/auth'));
            Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

            $this->actingAs($user)
                ->get(route('auth.google.reauthenticate'))
                ->assertRedirect('https://accounts.google.com/auth')
                ->assertSessionHas('google_reauthentication', true);
        });

        it('Google で確認できないユーザーは確認画面へ戻す', function (bool $configured, ?string $googleId, bool $hasPassword) {
            configureGoogleOAuth($configured);
            $user = User::factory()->create(['google_id' => $googleId, ...($hasPassword ? [] : ['password' => null])]);

            $this->actingAs($user)
                ->get(route('auth.google.reauthenticate'))
                ->assertRedirect(route('password.confirm'))
                ->assertSessionHas('error', 'Googleでの本人確認は現在利用できません。')
                ->assertSessionMissing('google_reauthentication');
        })->with([
            'Google 未連携' => [true, null, false],
            'Google ログイン未設定' => [false, 'google-123', false],
            'パスワードあり' => [true, 'google-123', true],
        ]);

        it('ゲストは Google での本人確認を始められない', function () {
            $this->get(route('auth.google.reauthenticate'))->assertRedirect(route('login'));
        });

        it('連携済みの Google アカウントで戻ると本人確認済みにして元の画面へ戻す', function () {
            $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
            fakeGoogleUser(['getId' => 'google-123']);
            mock(GoogleAuthServiceContract::class)->shouldNotReceive('findOrCreate');
            Log::spy();

            $sessionId = str_repeat('a', 40);

            $this->actingAs($user)
                ->withCookie(config('session.cookie'), $sessionId)
                ->withSession(['google_reauthentication' => true, 'url.intended' => route('security.edit')])
                ->get(route('auth.google.callback'))
                ->assertRedirect(route('security.edit'))
                ->assertSessionHas('auth.password_confirmed_at')
                ->assertSessionMissing('google_reauthentication');

            expect(app('session.store')->getId())->not->toBe($sessionId);
            $this->assertAuthenticatedAs($user);
            Log::shouldHaveReceived('info')->with('User confirmed identity with Google.', ['user_id' => $user->getKey()])->once();
        });

        it('別の Google アカウントで戻ると本人確認済みにしない', function () {
            $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
            fakeGoogleUser(['getId' => 'google-other']);

            $this->actingAs($user)
                ->withSession(['google_reauthentication' => true])
                ->get(route('auth.google.callback'))
                ->assertRedirect(route('password.confirm'))
                ->assertSessionHas('error', '連携済みのGoogleアカウントで本人確認してください。')
                ->assertSessionMissing('auth.password_confirmed_at');
        });

        it('確認を始めた後にパスワードが設定されていれば本人確認済みにしない', function () {
            $user = User::factory()->create(['google_id' => 'google-123']);
            fakeGoogleUser(['getId' => 'google-123']);

            $this->actingAs($user)
                ->withSession(['google_reauthentication' => true])
                ->get(route('auth.google.callback'))
                ->assertRedirect(route('password.confirm'))
                ->assertSessionHas('error', 'Googleでの本人確認は現在利用できません。')
                ->assertSessionMissing('auth.password_confirmed_at');
        });

        it('Google からの応答を取得できなければ本人確認済みにしない', function () {
            $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
            $driver = Mockery::mock(GoogleProvider::class);
            $driver->allows('user')->andThrow(new RuntimeException('invalid state'));
            Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

            $this->actingAs($user)
                ->withSession(['google_reauthentication' => true])
                ->get(route('auth.google.callback'))
                ->assertRedirect(route('password.confirm'))
                ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。')
                ->assertSessionMissing('auth.password_confirmed_at');
        });

        it('本人確認を始めていないログイン済みユーザーのコールバックはホームへ戻す', function () {
            $user = User::factory()->create(['google_id' => 'google-123']);
            Socialite::shouldReceive('driver')->never();

            $this->actingAs($user)
                ->get(route('auth.google.callback'))
                ->assertRedirect(config('fortify.home'))
                ->assertSessionMissing('auth.password_confirmed_at');
        });
    });
});

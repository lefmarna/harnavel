<?php

declare(strict_types=1);

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
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

    it('callback() は google id が null の場合にログインせずログイン画面へ戻す', function () {
        $existingUser = User::factory()->create(['google_id' => null]);

        fakeGoogleUser([
            'getId' => null,
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
    });

    it('callback() は google id が空文字の場合にログインせずログイン画面へ戻す', function () {
        fakeGoogleUser([
            'getId' => '',
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
    });

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

    it('login view は Google OAuth 設定不足時に canGoogleLogin=false を返す', function () {
        config(['services.google.redirect' => null]);

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/login')
                ->where('canGoogleLogin', false));
    });

    it('login view は Google OAuth 設定完備時に canGoogleLogin=true を返す', function () {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret', 'services.google.redirect' => 'https://example.com/callback']);

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/login')
                ->where('canGoogleLogin', true));
    });

    it('callback() でログイン完了して fortify.home へリダイレクトする', function () {
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
            ->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($existingUser);
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

    it('callback() は Google プロフィール名が null の場合もそのままサービスへ渡す', function () {
        $existingUser = User::factory()->create(['email' => 'test@example.com']);

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => null,
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->with('google-123', 'test@example.com', null)
            ->andReturn($existingUser);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(config('fortify.home'));

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

    it('callback() で QueryException が発生してもセッションエラーに SQL が露出しない', function () {
        $secretSql = 'INSERT INTO `users` (`email`, `secret_data`) VALUES (?, ?)';

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new QueryException('mysql', $secretSql, [], new Exception));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。');

        expect(session('error'))->not->toContain($secretSql);
    });

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
            ->andThrow(new GoogleLinkConfirmationPendingException($existingUser, 'google-123', 'existing@example.com'));

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

    it('callback() で想定外例外が発生しても安全なメッセージでログイン画面に戻す', function () {
        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new LogicException('unexpected failure'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google認証に失敗しました。もう一度お試しください。');
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

    it('callback() で QueryException が発生してもログにバインディング値が残らない', function () {
        Log::spy();

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'leaked@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new QueryException(
                'mysql',
                'insert into `users` (`email`, `google_id`) values (?, ?)',
                ['leaked@example.com', 'google-123'],
                new Exception('SQLSTATE[23000]: Integrity constraint violation'),
            ));

        $this->get(route('auth.google.callback'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google OAuth database error.'
                    && $context === ['exception_class' => QueryException::class];
            })
            ->once();
    });

    it('callback() で想定外例外が発生してもログには例外クラス名だけを残す', function () {
        Log::spy();

        fakeGoogleUser([
            'getId' => 'google-123',
            'getEmail' => 'test@example.com',
            'getName' => 'Test User',
            'getRaw' => ['email_verified' => true],
        ]);

        mock(GoogleAuthServiceContract::class)
            ->shouldReceive('findOrCreate')
            ->andThrow(new LogicException('unexpected failure'));

        $this->get(route('auth.google.callback'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google OAuth unexpected error.'
                    && $context === ['exception_class' => LogicException::class];
            })
            ->once();
    });

    it('shared auth data は google_id と認証機密情報をフロントへ公開しない', function () {
        $user = User::factory()->create([
            'google_id' => 'secret-google-id-12345',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->has('auth.user')
                ->missing('auth.user.google_id')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->where('auth.hasPassword', true)
            );
    });
});

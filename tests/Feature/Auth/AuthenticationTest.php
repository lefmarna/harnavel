<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

describe('ログイン', function () {
    it('ログイン画面を表示できる', function () {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('auth/login')
                    ->where('seo.title', 'ログイン')
                    ->where('seo.description', 'メールアドレスとパスワードを入力してログインします。')
            );
    });

    it('ログイン画面から認証できる', function () {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));
    });

    it('無効なパスワードでは認証できない', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => 'ログイン用の認証情報が正しくありません。再度お試しください。']);

        $this->assertGuest();
    });

    it('ログアウトできる', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    });

    it('レート制限される', function () {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    });

    it('同じアカウントへのログイン失敗は IP が違っても1時間に20回までに制限される', function () {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::DEFAULT_PASSWORD])
            ->assertTooManyRequests();
    });

    it('ログインに成功した試行はアカウント単位の上限に数えない', function () {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 19; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::DEFAULT_PASSWORD]);
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        expect($this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->getStatusCode())->not->toBe(429);

        expect($this->withServerVariables(['REMOTE_ADDR' => '198.51.100.3'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->getStatusCode())->toBe(429);
    });

    it('パスワードを再設定するとアカウント単位のログイン制限を解除する', function () {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertSessionHasNoErrors();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password1'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    });
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

describe('パスワード再設定', function () {
    it('パスワード再設定リンク画面を表示できる', function () {
        $response = $this->get(route('password.request'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('auth/forgot-password')
                    ->where('seo.title', 'パスワードをお忘れの方')
                    ->where('seo.description', 'メールアドレスを入力してパスワード再設定リンクを受け取ります。')
            );
    });

    it('パスワード再設定リンクをリクエストできる', function () {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('パスワード再設定画面を表示できる', function () {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response
                ->assertOk()
                ->assertInertia(
                    fn (Assert $page) => $page
                        ->component('auth/reset-password')
                        ->where('seo.title', 'パスワード再設定')
                        ->where('seo.description', 'アカウントの新しいパスワードを設定します。')
                );

            return true;
        });
    });

    it('有効なトークンでパスワードを再設定できる', function () {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    });

    it('bcrypt形式の文字列でパスワード再設定しても平文としてハッシュ化される', function () {
        Notification::fake();

        $user = User::factory()->create();
        $password = Hash::make('known-plain');

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $password) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $storedPassword = $user->refresh()->password;

            expect($storedPassword)
                ->not->toBe($password)
                ->and(Hash::check($password, $storedPassword))->toBeTrue()
                ->and(Hash::check('known-plain', $storedPassword))->toBeFalse();

            return true;
        });
    });

    it('無効なトークンではパスワードを再設定できない', function () {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    });
});

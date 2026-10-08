<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    // 応答の違いから、そのメールアドレスが登録済みかどうかを判別させない。
    it('未登録や送信直後のメールアドレスにも送信時と同じ応答を返す', function (Closure $email) {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email()])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '登録済みのメールアドレスであれば、パスワード再設定メールを送信しました。');
    })->with([
        '登録済み' => [fn (): string => User::factory()->create()->email],
        '未登録' => [fn (): string => 'unknown@example.com'],
        '送信直後で再送できない' => [function (): string {
            $user = User::factory()->create();
            Password::broker()->sendResetLink(['email' => $user->email]);

            return $user->email;
        }],
    ]);

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

    // 窃取されたセッションは、本人が再設定した時点で使えなくする。
    it('パスワードを再設定すると全端末のセッションを失効させる', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        insertDatabaseSession(str_repeat('a', 40), $user);
        insertDatabaseSession(str_repeat('b', 40), $otherUser);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertSessionHasNoErrors();

        expect(databaseSessionIds())->toBe([str_repeat('b', 40)]);
    });

    it('長いメールアドレスでも DB キャッシュへ書き込んで再設定できる', function () {
        config(['cache.default' => 'database']);
        // 形式チェックを通る長さで、キャッシュキーの列長 255 を超えうるもの。
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 62).'.'.str_repeat('d', 40).'.com';
        $user = User::factory()->create(['email' => $email]);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $email,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertSessionHasNoErrors()->assertRedirect();
    });
});

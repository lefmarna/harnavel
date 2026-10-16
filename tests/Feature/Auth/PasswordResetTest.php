<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    it('パスワードを再設定すると全端末のセッションを失効させる', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $sessionId = loginFromNewDevice($user);
        $otherUsersSessionId = loginFromNewDevice($otherUser);

        forgetRequestState();
        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertSessionHasNoErrors();

        visitDashboardFrom($sessionId)->assertRedirect(route('login'));
        visitDashboardFrom($otherUsersSessionId)->assertOk();
    });

    // 他人のメールで先に登録した第三者の資格情報を、持ち主が再設定で取り戻した後に残さない（TADR-0016）。
    it('メール未確認のアカウントを再設定するとパスキーと Google 連携を外して確認済みにする', function () {
        $user = User::factory()->unverified()->create(['google_id' => 'registrant-google']);
        $user->passkeys()->create([
            'name' => '先に登録した第三者のパスキー',
            'credential_id' => 'registrant-credential',
            'credential' => [],
        ]);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'owner-password1',
            'password_confirmation' => 'owner-password1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $user->refresh();

        expect(Hash::check('owner-password1', $user->password))->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->google_id)->toBeNull()
            ->and($user->passkeys()->count())->toBe(0);
    });

    // 旧アドレス宛てのトークンで、変更後のアドレスまで所有を示したことにさせない。
    it('トークンを確かめた後にメールアドレスが変わっていたら再設定しない', function () {
        $user = User::factory()->unverified()->create(['email' => 'owner@example.com']);
        $password = $user->password;
        $token = Password::createToken($user);

        // Fortify がトークンの照合用にユーザーを読み込んだ直後に、別のリクエストがメールを変えたことにする。
        $changed = false;
        User::retrieved(function (User $retrieved) use (&$changed): void {
            if ($changed) {
                return;
            }

            $changed = true;
            DB::table('users')->where('id', $retrieved->getKey())->update(['email' => 'victim@example.com']);
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'owner-password1',
            'password_confirmation' => 'owner-password1',
        ])->assertSessionHasErrors('email');

        $user->refresh();

        expect($user->email)->toBe('victim@example.com')
            ->and($user->email_verified_at)->toBeNull()
            ->and($user->password)->toBe($password);
    });

    it('トークンを確かめた後に本人がパスワードを変えていたら再設定しない', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $changed = false;
        DB::listen(function (QueryExecuted $query) use (&$changed, $user): void {
            if ($changed || ! str_contains($query->sql, 'password_reset_tokens')) {
                return;
            }

            $changed = true;
            DB::table('users')->where('id', $user->getKey())->update(['password' => Hash::make('owner-password2')]);
            Password::deleteToken($user);
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'attacker-password1',
            'password_confirmation' => 'attacker-password1',
        ])->assertSessionHasErrors(['email' => trans('passwords.token')]);

        expect(Hash::check('owner-password2', $user->refresh()->password))->toBeTrue();
    });

    // 使ったトークンが残ると、メールを旧アドレスへ戻したときに再利用できてしまう。
    it('再設定の直後にメールアドレスが変わっても使ったトークンを消す', function () {
        $user = User::factory()->unverified()->create(['email' => 'owner@example.com']);
        $token = Password::createToken($user);

        // 再設定を保存した直後に、別のリクエストがメールを変えたことにする。
        $changed = false;
        Event::listen(TransactionCommitted::class, function () use (&$changed, $user): void {
            if ($changed) {
                return;
            }

            $changed = true;
            DB::table('users')->where('id', $user->getKey())->update(['email' => 'changed@example.com']);
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'owner-password1',
            'password_confirmation' => 'owner-password1',
        ])->assertSessionHasNoErrors();

        expect(DB::table('password_reset_tokens')->where('email', 'owner@example.com')->exists())->toBeFalse();
    });

    it('メール確認済みのアカウントを再設定してもパスキーと Google 連携を残す', function () {
        $user = User::factory()->create(['google_id' => 'owner-google']);
        $user->passkeys()->create([
            'name' => '本人のパスキー',
            'credential_id' => 'owner-credential',
            'credential' => [],
        ]);

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'owner-password1',
            'password_confirmation' => 'owner-password1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $user->refresh();

        expect($user->google_id)->toBe('owner-google')
            ->and($user->passkeys()->count())->toBe(1);
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

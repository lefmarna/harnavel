<?php

declare(strict_types=1);

use App\Mail\VerifyNewEmailMail;
use App\Models\User;
use App\ViewModels\Settings\ProfilePageViewModel;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProfilePageViewModel::class);

describe('プロフィール設定', function () {
    beforeEach(function () {
        $this->withSession(['auth.password_confirmed_at' => time()]);
    });

    it('プロフィール設定画面を表示できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('settings/profile')
                    ->where('mustVerifyEmail', true)
                    ->where('status', null)
                    ->where('seo.title', 'プロフィール設定')
                    ->where('seo.description', 'ニックネームとメールアドレスを管理します。')
            );
    });

    it('ニックネームを更新できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->name)->toBe('Test User');
    });

    it('プロフィール更新時の新しいメールアドレスは小文字にして確認メールを送る', function () {
        Mail::fake();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test.User@Example.COM',
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(VerifyNewEmailMail::class, fn (VerifyNewEmailMail $mail): bool => $mail->hasTo('test.user@example.com'));
    });

    it('lowercase usernamesが無効なら新しいメールアドレスの大文字小文字を維持して確認メールを送る', function () {
        config(['fortify.lowercase_usernames' => false]);
        Mail::fake();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test.User@Example.COM',
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(VerifyNewEmailMail::class, fn (VerifyNewEmailMail $mail): bool => $mail->hasTo('Test.User@Example.COM'));
    });

    it('lowercase usernamesが無効ならメールアドレスの大文字小文字だけの変更ではメール認証状態を維持する', function () {
        config(['fortify.lowercase_usernames' => false]);

        $user = User::factory()->create([
            'email' => 'test.user@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'Test.User@Example.COM',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->email)->toBe('Test.User@Example.COM');
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('メールアドレスが変わらない場合はメール認証状態を維持する', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->email_verified_at)->not->toBeNull();
    });

    it('プロフィール更新の検証文言を表示する', function (array $payload, string $field, string $expectedMessage) {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), $payload);

        $response->assertSessionHasErrors([$field => $expectedMessage]);
    })->with([
        'ニックネーム未入力' => [
            ['email' => 'test@example.com'],
            'name',
            'ニックネームを入力してください。',
        ],
        'ニックネームが文字列でない' => [
            ['name' => ['array'], 'email' => 'test@example.com'],
            'name',
            'ニックネームは文字列で入力してください。',
        ],
        'ニックネームが長すぎる' => [
            ['name' => str_repeat('a', 256), 'email' => 'test@example.com'],
            'name',
            'ニックネームは255文字以内で入力してください。',
        ],
        'メールアドレス未入力' => [
            ['name' => 'Test User'],
            'email',
            'メールアドレスを入力してください。',
        ],
        'メールアドレスが文字列でない' => [
            ['name' => 'Test User', 'email' => ['array']],
            'email',
            'メールアドレスは文字列で入力してください。',
        ],
        'メールアドレスの形式が不正' => [
            ['name' => 'Test User', 'email' => 'not-an-email'],
            'email',
            '有効なメールアドレスを入力してください。',
        ],
        'メールアドレスが長すぎる' => [
            ['name' => 'Test User', 'email' => str_repeat('a', 244).'@example.com'],
            'email',
            'メールアドレスは255文字以内で入力してください。',
        ],
    ]);

    it('既に使われているメールアドレスの検証文言を表示する', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $other->email,
            ]);

        $response->assertSessionHasErrors([
            'email' => 'このメールアドレスは既に使用されています。',
        ]);
    });

    // 本人確認はパスワード・パスキー・Google のどれでも済む。退会でパスワードを重ねて求めると、
    // パスキーや Google で確認した利用者が選んだ手段では退会できなくなる。
    it('本人確認済みならパスワードを入力せずにアカウントを削除できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        expect($user->fresh())->toBeNull();
    });

    it('メール未確認のユーザーもアカウントを削除できる', function () {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertRedirect(route('home'));

        expect($user->fresh())->toBeNull();
    });

    // 本人がパスワードを再設定した後に、その前から走っていた削除を通さない（TADR-0016）。
    it('削除の途中でセッションが失効したら、その削除を拒否する', function () {
        $user = User::factory()->create();
        $rememberToken = null;
        Event::listen(RouteMatched::class, function () use ($user, &$rememberToken): void {
            $fresh = User::findOrFail($user->getKey());
            $fresh->revokeSessions();
            $fresh->save();
            $rememberToken = $fresh->remember_token;
        });

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertConflict();

        // 失効の後に本人がログイン保持で入り直していても、そのトークンを無効にしない。
        expect($user->fresh())->not->toBeNull()
            ->remember_token->toBe($rememberToken);
    });
});

// セッションを奪っただけの第三者に、メールアドレスを書き換えて再設定メールを受け取らせない。
describe('プロフィール設定の本人確認', function () {
    it('直近の本人確認がなければ確認画面へ誘導する', function (string $method, string $route, array $payload) {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->call($method, route($route), $payload)
            ->assertRedirect(route('password.confirm'));

        expect($user->fresh())
            ->not->toBeNull()
            ->email->toBe('owner@example.com');
    })->with([
        '表示' => ['GET', 'profile.edit', []],
        'メールアドレスの変更' => ['PATCH', 'profile.update', ['name' => 'Attacker', 'email' => 'attacker@example.com']],
        'アカウント削除' => ['DELETE', 'profile.destroy', []],
    ]);

    it('Google 連携済みのパスワード未設定ユーザーも確認なしにアカウントを削除できない', function () {
        configureGoogleOAuth(true);
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertRedirect(route('password.confirm'));

        expect($user->fresh())->not->toBeNull();
    });
});

describe('設定トップ', function () {
    it('開くとプロフィール設定へ転送する', function () {
        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertRedirect(route('profile.edit'));
    });

    it('画面を開く以外のメソッドは受け付けない', function (string $method) {
        $this->actingAs(User::factory()->create())
            ->call($method, '/settings')
            ->assertMethodNotAllowed();
    })->with(['POST', 'PUT', 'PATCH', 'DELETE']);
});

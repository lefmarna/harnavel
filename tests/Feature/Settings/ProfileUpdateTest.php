<?php

declare(strict_types=1);

use App\Models\User;
use App\ViewModels\Settings\ProfilePageViewModel;
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
                    ->where('seo.description', '名前とメールアドレスを管理します。')
            );
    });

    it('プロフィール情報を更新できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');
        expect($user->email_verified_at)->toBeNull();
    });

    it('プロフィール更新時のメールアドレスは小文字で保存される', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test.User@Example.COM',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        expect($user->email)->toBe('test.user@example.com');
        expect($user->email_verified_at)->toBeNull();
    });

    it('lowercase usernamesが無効ならプロフィール更新時にメールアドレスの大文字小文字を維持する', function () {
        config(['fortify.lowercase_usernames' => false]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test.User@Example.COM',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->email)->toBe('Test.User@Example.COM');
        expect($user->email_verified_at)->toBeNull();
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
        '名前未入力' => [
            ['email' => 'test@example.com'],
            'name',
            '名前を入力してください。',
        ],
        '名前が文字列でない' => [
            ['name' => ['array'], 'email' => 'test@example.com'],
            'name',
            '名前は文字列で入力してください。',
        ],
        '名前が長すぎる' => [
            ['name' => str_repeat('a', 256), 'email' => 'test@example.com'],
            'name',
            '名前は255文字以内で入力してください。',
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

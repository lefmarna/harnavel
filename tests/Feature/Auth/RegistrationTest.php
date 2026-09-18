<?php

declare(strict_types=1);

use App\Models\User;
use App\ViewModels\WelcomePageViewModel;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WelcomePageViewModel::class);

describe('ユーザー登録', function () {
    it('登録機能が有効なときホームページは登録導線を公開する', function () {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canRegister', true)
            );
    });

    it('登録画面を表示できる', function () {
        $response = $this->get(route('register'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('auth/register')
                    ->where('seo.title', '新規登録')
                    ->where('seo.description', '必要事項を入力して新しいアカウントを作成します。')
            );
    });

    it('新規ユーザーを登録できる', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    });

    it('bcrypt形式の文字列をパスワードとして登録しても平文としてハッシュ化される', function () {
        $password = Hash::make('known-plain');

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(config('fortify.home'));

        $storedPassword = User::query()->where('email', 'test@example.com')->value('password');

        expect($storedPassword)
            ->not->toBe($password)
            ->and(Hash::check($password, $storedPassword))->toBeTrue()
            ->and(Hash::check('known-plain', $storedPassword))->toBeFalse();
    });

    it('登録時のメールアドレスは小文字で保存される', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'Test.User@Example.COM',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));

        // DB の照合順序は大文字小文字を区別しないため assertDatabaseHas では小文字化を検証できない。
        // 実際に格納された値を厳密比較する。
        expect(User::query()->value('email'))->toBe('test.user@example.com');
    });

    it('lowercase usernamesが無効なら登録時にメールアドレスの大文字小文字を維持する', function () {
        config(['fortify.lowercase_usernames' => false]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'Test.User@Example.COM',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));

        expect(User::query()->value('email'))->toBe('Test.User@Example.COM');
    });
});

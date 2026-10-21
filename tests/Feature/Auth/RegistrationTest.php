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
                ->where('canRegister', true)
            );
    });

    it('検証文言では画面と同じ項目名を使う', function () {
        $this->post(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertSessionHasErrors([
            'name' => 'ニックネームは必須項目です。',
            'email' => 'メールアドレスは、有効なメールアドレス形式で指定してください。',
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

    it('新規ユーザーを登録し、メールアドレスはログインと同じ規則でそろえて保存する', function (bool $lowercase, string $expected) {
        config(['fortify.lowercase_usernames' => $lowercase]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'Test.User@Example.COM',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(config('fortify.home'));

        // DB の照合順序は大文字小文字を区別しないため assertDatabaseHas では小文字化を検証できない。
        expect(User::query()->sole())
            ->name->toBe('Test User')
            ->email->toBe($expected);
    })->with([
        '小文字化が有効' => [true, 'test.user@example.com'],
        '小文字化が無効' => [false, 'Test.User@Example.COM'],
    ]);
});

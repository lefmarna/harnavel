<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

describe('パスワード設定', function () {
    it('パスワード更新画面を表示できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('user-password.edit'));

        $response
            ->assertOk()
            ->assertInertia(
                fn ($page) => assertInertiaShape($page, 'settings/password', [
                    'seo.title',
                    'seo.description',
                ])
                    ->where('seo.title', 'パスワード設定')
                    ->where('seo.description', 'アカウントのパスワードを更新します。')
            );
    });

    it('パスワードを更新できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user-password.edit'));

        expect(Hash::check('new-password1', $user->refresh()->password))->toBeTrue();
    });

    it('bcrypt形式の文字列でパスワード更新しても平文としてハッシュ化される', function () {
        $user = User::factory()->create();
        $password = Hash::make('known-plain');

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user-password.edit'));

        $storedPassword = $user->refresh()->password;

        expect($storedPassword)
            ->not->toBe($password)
            ->and(Hash::check($password, $storedPassword))->toBeTrue()
            ->and(Hash::check('known-plain', $storedPassword))->toBeFalse();
    });

    it('パスワード更新の検証文言を表示する', function (array $payload, string $field, string $expectedMessage) {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), $payload);

        $response->assertSessionHasErrors([$field => $expectedMessage]);
    })->with([
        '現在のパスワード未入力' => [
            ['password' => 'new-password1', 'password_confirmation' => 'new-password1'],
            'current_password',
            '現在のパスワードを入力してください。',
        ],
        '現在のパスワードが文字列でない' => [
            ['current_password' => 12345678, 'password' => 'new-password1', 'password_confirmation' => 'new-password1'],
            'current_password',
            '現在のパスワードは文字列で入力してください。',
        ],
        '現在のパスワードが不一致' => [
            ['current_password' => 'wrong-password', 'password' => 'new-password1', 'password_confirmation' => 'new-password1'],
            'current_password',
            '現在のパスワードが正しくありません。',
        ],
        '新しいパスワード未入力' => [
            ['current_password' => UserFactory::DEFAULT_PASSWORD],
            'password',
            '新しいパスワードを入力してください。',
        ],
        '新しいパスワードが文字列でない' => [
            ['current_password' => UserFactory::DEFAULT_PASSWORD, 'password' => 12345678],
            'password',
            '新しいパスワードは文字列で入力してください。',
        ],
        '確認用パスワードが不一致' => [
            ['current_password' => UserFactory::DEFAULT_PASSWORD, 'password' => 'new-password1', 'password_confirmation' => 'other-password1'],
            'password',
            'パスワード確認が一致しません。',
        ],
        // 既定メッセージ側の :attribute。この画面だけ「新しいパスワード」で、
        // lang/ja/validation.php の attributes.password（「パスワード」）を上書きしている。
        '既定メッセージの属性名' => [
            ['current_password' => UserFactory::DEFAULT_PASSWORD, 'password' => 'abc1234', 'password_confirmation' => 'abc1234'],
            'password',
            '新しいパスワードの文字数は、8文字以上である必要があります。',
        ],
    ]);

    it('パスワード更新には現在の正しいパスワードが必要', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('user-password.edit'));
    });
});

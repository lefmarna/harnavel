<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

describe('セキュリティ設定', function () {
    it('セキュリティ設定画面を表示できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response
            ->assertOk()
            ->assertInertia(
                fn ($page) => assertInertiaShape($page, 'settings/security', [
                    'canManagePasskeys',
                    'passkeys',
                    'seo.title',
                    'seo.description',
                ])
                    ->where('canManagePasskeys', true)
                    ->where('passkeys', [])
                    ->where('seo.title', 'セキュリティ設定')
                    ->where('seo.description', 'パスワードとパスキーを管理します。')
            );
    });

    it('パスワードを更新できる', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        expect(Hash::check('new-password1', $user->refresh()->password))->toBeTrue();
    });

    it('bcrypt形式の文字列でパスワード更新しても平文としてハッシュ化される', function () {
        $user = User::factory()->create();
        $password = Hash::make('known-plain');

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

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
            ->from(route('security.edit'))
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
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    });

    it('登録済みのパスキーを一覧で返す', function () {
        $user = User::factory()->create();

        $user->passkeys()->create([
            'name' => 'MacBook Pro',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('passkeys', 1)
                ->where('passkeys.0.name', 'MacBook Pro')
                ->where('passkeys.0.last_used_at_diff', null)
                ->has('passkeys.0.created_at_diff')
            );
    });

    it('パスワードを持つユーザーはパスワード確認を求められる', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    });

    it('パスワード未設定のユーザーはパスワード確認なしで到達できる', function () {
        $user = User::factory()->create(['password' => null]);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk();
    });

    it('パスキーを削除できる', function () {
        $user = User::factory()->create();

        $passkey = $user->passkeys()->create([
            'name' => 'iPhone',
            'credential_id' => 'credential-2',
            'credential' => ['id' => 'credential-2'],
        ]);

        $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertRedirect();

        expect($user->passkeys()->count())->toBe(0);
    });

    it('他人のパスキーは削除できない', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $passkey = $other->passkeys()->create([
            'name' => 'Someone else',
            'credential_id' => 'credential-3',
            'credential' => ['id' => 'credential-3'],
        ]);

        $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertForbidden();

        expect($other->passkeys()->count())->toBe(1);
    });

    it('パスキーを持つパスワード未設定ユーザーはパスワード確認を求められる', function () {
        $user = User::factory()->create(['password' => null]);

        $user->passkeys()->create([
            'name' => 'Google のみのユーザーのパスキー',
            'credential_id' => 'credential-4',
            'credential' => ['id' => 'credential-4'],
        ]);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    });

    it('パスワード確認の有効期限に auth.password_timeout を使う', function () {
        config(['auth.password_timeout' => 60]);

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time() - 120])
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    });
});

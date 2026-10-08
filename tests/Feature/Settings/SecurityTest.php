<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePasswordWhenSet;
use App\Models\User;
use App\ViewModels\Settings\SecurityPageViewModel;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

mutates(RequirePasswordWhenSet::class, SecurityPageViewModel::class);

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
            ->withSession(['auth.password_confirmed_at' => time()])
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

    // 乗っ取りに気づいて変更しても、攻撃者の端末がログインしたまま残らないようにする。
    it('パスワードを更新すると他の端末のセッションとログイン保持を失効させる', function () {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $otherUser = User::factory()->create();
        $currentSessionId = str_repeat('a', 40);

        insertDatabaseSession($currentSessionId, $user);
        insertDatabaseSession(str_repeat('b', 40), $user);
        insertDatabaseSession(str_repeat('c', 40), $otherUser);

        $this
            ->actingAs($user)
            ->withCookie(config('session.cookie'), $currentSessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasNoErrors();

        // 本人の Cookie を盗んだ第三者も同じ ID を持つため、本人のログインは保ったまま ID を作り直す。
        expect(databaseSessionIds())->not->toContain(str_repeat('b', 40))
            ->toContain(str_repeat('c', 40))
            ->and(app('session.store')->getId())->not->toBe($currentSessionId)
            ->and($user->refresh()->remember_token)->not->toBe('old-remember-token');
        $this->assertAuthenticatedAs($user);
    });

    it('bcrypt形式の文字列でパスワード更新しても平文としてハッシュ化される', function () {
        $user = User::factory()->create();
        $password = Hash::make('known-plain');

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
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
            ->withSession(['auth.password_confirmed_at' => time()])
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
            ->withSession(['auth.password_confirmed_at' => time()])
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

    it('資格情報を持たないユーザーはパスワード確認なしで到達できる', function () {
        $user = User::factory()->create(['password' => null, 'google_id' => null]);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk();
    });

    // 設定の不備で確認手段が消えても、セッションを奪った第三者を素通りさせない。
    it('Google ログインが使えなくても Google 連携済みのユーザーには確認を求める', function () {
        configureGoogleOAuth(false);
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    });

    // セッションを奪っただけの第三者が、最初の資格情報を登録して居座れないようにする。
    it('Google 連携済みのパスワード未設定ユーザーは Google での確認を求められる', function () {
        configureGoogleOAuth(true);
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));

        $this
            ->actingAs($user)
            ->put(route('user-password.update'), [
                'password' => 'attacker-password1',
                'password_confirmation' => 'attacker-password1',
            ])
            ->assertRedirect(route('password.confirm'));

        expect($user->refresh()->password)->toBeNull();
    });

    it('ゲストがパスワード確認付きルートに到達した場合は確認画面へ誘導する', function () {
        Route::middleware(['web', 'password.confirm'])->get('/_spec/password-confirm', fn (): string => 'ok');

        $this->get('/_spec/password-confirm')->assertRedirect(route('password.confirm'));
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

    it('パスキーを持つパスワード未設定ユーザーは確認なしにパスワードを設定できない', function () {
        $user = User::factory()->create(['password' => null]);

        $user->passkeys()->create([
            'name' => 'パスキーのみのユーザー',
            'credential_id' => 'credential-5',
            'credential' => ['id' => 'credential-5'],
        ]);

        $this
            ->actingAs($user)
            ->put(route('user-password.update'), [
                'password' => 'attacker-password1',
                'password_confirmation' => 'attacker-password1',
            ])
            ->assertRedirect(route('password.confirm'));

        expect($user->refresh()->password)->toBeNull();
    });

    it('資格情報を持たないユーザーは確認なしで最初のパスワードを設定できる', function () {
        $user = User::factory()->create(['password' => null]);

        $this
            ->actingAs($user)
            ->put(route('user-password.update'), [
                'password' => 'first-password1',
                'password_confirmation' => 'first-password1',
            ])
            ->assertSessionHasNoErrors();

        expect($user->refresh()->password)->not->toBeNull();
    });
});

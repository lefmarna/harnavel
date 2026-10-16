<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePasswordWhenSet;
use App\Models\User;
use App\ViewModels\Settings\SecurityPageViewModel;
use Database\Factories\UserFactory;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
                    'canRegisterPasskey',
                    'passkeys',
                    'googleLinked',
                    'canUnlinkGoogle',
                    'seo.title',
                    'seo.description',
                ])
                    ->where('canManagePasskeys', true)
                    ->where('canRegisterPasskey', true)
                    ->where('passkeys', [])
                    ->where('seo.title', 'セキュリティ設定')
                    ->where('seo.description', 'パスワード、パスキー、Googleログイン連携を管理します。')
            );
    });

    it('パスワードを更新すると他の端末のセッションとログイン保持を失効させる', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $otherUser = User::factory()->create();
        $otherDeviceSessionId = loginFromNewDevice($user);
        $otherUsersSessionId = loginFromNewDevice($otherUser);
        $currentSessionId = loginFromNewDevice($user);

        forgetRequestState();
        $this
            ->withCookie(config('session.cookie'), $currentSessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasNoErrors();
        $renewedSessionId = lastSessionId();

        expect($renewedSessionId)->not->toBe($currentSessionId)
            ->and($user->refresh()->remember_token)->not->toBe('old-remember-token');
        visitDashboardFrom($renewedSessionId)->assertOk();
        visitDashboardFrom($currentSessionId)->assertRedirect(route('login'));
        visitDashboardFrom($otherDeviceSessionId)->assertRedirect(route('login'));
        visitDashboardFrom($otherUsersSessionId)->assertOk();
    });

    it('パスワードを更新すると、それ以前に発行した再設定リンクを使えなくする', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasNoErrors();
        Auth::logout();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'attacker-password1',
            'password_confirmation' => 'attacker-password1',
        ])->assertSessionHasErrors(['email' => trans('passwords.token')]);

        expect(Hash::check('new-password1', $user->refresh()->password))->toBeTrue();
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

    it('Google ログインが使えなくても Google 連携済みのユーザーには確認を求める', function () {
        configureGoogleOAuth(false);
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);

        $this
            ->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    });

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

    it('パスキーを削除し、他の端末のセッションを失効させ、今の端末はログインを保つ', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Attacker key',
            'credential_id' => 'credential-2',
            'credential' => ['id' => 'credential-2'],
        ]);
        $otherDeviceSessionId = loginFromNewDevice($user);
        $currentSessionId = loginFromNewDevice($user);

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $currentSessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertRedirect();
        $renewedSessionId = lastSessionId();

        expect($renewedSessionId)->not->toBe($currentSessionId)
            ->and($user->passkeys()->count())->toBe(0);
        visitDashboardFrom($renewedSessionId)->assertOk();
        visitDashboardFrom($otherDeviceSessionId)->assertRedirect(route('login'));
    });

    it('パスキー削除の途中でセッションが失効したら、その削除を拒否する', function () {
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'iPhone',
            'credential_id' => 'credential-2',
            'credential' => ['id' => 'credential-2'],
        ]);
        Event::listen(RouteMatched::class, function () use ($user): void {
            $fresh = User::findOrFail($user->getKey());
            $fresh->revokeSessions();
            $fresh->save();
        });

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertConflict();

        expect($user->passkeys()->count())->toBe(1)
            ->and($user->refresh()->session_generation)->toBe(1);
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

    it('メール未確認のユーザーにはパスキーの登録を出さない', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn ($page) => $page->where('canRegisterPasskey', false));
    });

    it('メール未確認のユーザーもセキュリティ設定を使える', function (string $method, string $routeName, array $payload) {
        $user = User::factory()->unverified()->create(['google_id' => 'google-1']);

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->call($method, route($routeName), $payload);

        $response->assertSessionHasNoErrors();
        expect($response->getStatusCode())->toBeIn([200, 302])
            ->and($response->headers->get('Location'))->not->toBe(route('verification.notice'));
    })->with([
        '画面' => ['GET', 'security.edit', []],
        'パスワード変更' => ['PUT', 'user-password.update', [
            'current_password' => UserFactory::DEFAULT_PASSWORD,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ]],
        'Google 連携の解除' => ['DELETE', 'security.google.destroy', []],
    ]);
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthService;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

// 不正ログインや乗っ取りを後から追えるよう、認証まわりの出来事を個人情報なしで残す（docs/logging-guidelines.md）。
describe('セキュリティイベントのログ', function () {
    beforeEach(function () {
        Log::spy();
    });

    it('ログインの成功と失敗を記録する', function () {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::DEFAULT_PASSWORD]);

        Log::shouldHaveReceived('info')->with('Login failed.', ['user_id' => $user->getKey()])->once();
        Log::shouldHaveReceived('info')->with('Login failed.', ['user_id' => null])->once();
        Log::shouldHaveReceived('info')->with('User logged in.', ['user_id' => $user->getKey(), 'remember' => false])->once();
    });

    it('ログアウトを記録する', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        Log::shouldHaveReceived('info')->with('User logged out.', ['user_id' => $user->getKey()])->once();
    });

    it('回数制限による拒否を警告として記録する', function () {
        for ($attempt = 0; $attempt <= 10; $attempt++) {
            $this->post(route('register.store'));
        }

        Log::shouldHaveReceived('warning')->with('Request was rate limited.')->once();
    });

    it('会員登録を記録する', function () {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        Log::shouldHaveReceived('info')->with('User registered.', ['user_id' => $user->getKey()])->once();
    });

    it('パスワードの再設定と変更を記録する', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => UserFactory::DEFAULT_PASSWORD,
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasNoErrors();

        forgetRequestState();
        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password2',
            'password_confirmation' => 'new-password2',
        ])->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')->with('User password was reset.', ['user_id' => $user->getKey()])->once();
        Log::shouldHaveReceived('info')->with('User password was changed.', ['user_id' => $user->getKey()])->once();
    });

    it('メールアドレスの変更を記録し、名前だけの変更は記録しない', function () {
        $user = User::factory()->create(['name' => 'Before', 'email' => 'before@example.com']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->patch(route('profile.update'), ['name' => 'After', 'email' => 'before@example.com']);
        Log::shouldNotHaveReceived('info', ['User email was changed.', ['user_id' => $user->getKey()]]);

        $this->patch(route('profile.update'), ['name' => 'After', 'email' => 'after@example.com']);
        Log::shouldHaveReceived('info')->with('User email was changed.', ['user_id' => $user->getKey()])->once();
    });

    it('アカウント削除を記録する', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.destroy'))
            ->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')->with('User account was deleted.', ['user_id' => $user->getKey()])->once();
    });

    it('パスキーの登録と削除を記録する', function () {
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'iPhone',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        event(new PasskeyRegistered($user, $passkey));
        event(new PasskeyDeleted($user, $passkey));

        $context = ['user_id' => $user->getKey(), 'passkey_id' => $passkey->getKey()];
        Log::shouldHaveReceived('info')->with('Passkey registered.', $context)->once();
        Log::shouldHaveReceived('info')->with('Passkey deleted.', $context)->once();
    });

    it('Google 連携と、先に登録された資格情報の無効化を記録する', function (bool $verified, bool $revoked) {
        $user = User::factory()->state(['email_verified_at' => $verified ? now() : null])->create([
            'email' => 'link@example.com',
        ]);

        app(GoogleAuthService::class)->confirmLink((string) $user->getKey(), 'google-123', 'link@example.com');

        Log::shouldHaveReceived('info')->with('Google account was linked.', [
            'user_id' => $user->getKey(),
            'existing_credentials_revoked' => $revoked,
        ])->once();
    })->with([
        'メール確認済み' => [true, false],
        'メール未確認' => [false, true],
    ]);
});

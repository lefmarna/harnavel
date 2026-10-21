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
            ->get(settingsUrl('profile'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('settings/profile')
                    ->where('mustVerifyEmail', true)
                    ->where('status', null)
                    ->where('seo.title', pageCopy('pages.settings.profile.title'))
                    ->where('seo.description', pageCopy('pages.settings.profile.description'))
            );
    });

    it('ニックネームを更新でき、メールアドレスが変わらなければ認証状態を維持する', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(settingsUrl('profile'));

        expect($user->refresh())
            ->name->toBe('Test User')
            ->email_verified_at->not->toBeNull();
    });

    it('新しいメールアドレスはログインと同じ規則でそろえて確認メールを送る', function (bool $lowercase, string $expected) {
        config(['fortify.lowercase_usernames' => $lowercase]);
        Mail::fake();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test.User@Example.COM',
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(VerifyNewEmailMail::class, fn (VerifyNewEmailMail $mail): bool => $mail->hasTo($expected));
    })->with([
        '小文字化が有効' => [true, 'test.user@example.com'],
        '小文字化が無効' => [false, 'Test.User@Example.COM'],
    ]);

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
            ->assertRedirect(settingsUrl('profile'));

        expect($user->refresh()->email)->toBe('Test.User@Example.COM');
        expect($user->email_verified_at)->not->toBeNull();
    });

    it('プロフィール更新の検証文言は登録画面と同じ共通の文言を使う', function (array $payload, string $field, string $expectedMessage) {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(settingsUrl('profile'))
            ->patch(route('profile.update'), $payload);

        $response->assertSessionHasErrors([$field => $expectedMessage]);
    })->with([
        'ニックネーム未入力' => [
            ['email' => 'test@example.com'],
            'name',
            'ニックネームは必須項目です。',
        ],
        'ニックネームが文字列でない' => [
            ['name' => ['array'], 'email' => 'test@example.com'],
            'name',
            'ニックネームには、文字列を指定してください。',
        ],
        'ニックネームが長すぎる' => [
            ['name' => str_repeat('a', 256), 'email' => 'test@example.com'],
            'name',
            'ニックネームの文字数は、255文字以下である必要があります。',
        ],
        'メールアドレス未入力' => [
            ['name' => 'Test User'],
            'email',
            'メールアドレスは必須項目です。',
        ],
        'メールアドレスが文字列でない' => [
            ['name' => 'Test User', 'email' => ['array']],
            'email',
            'メールアドレスには、文字列を指定してください。',
        ],
        'メールアドレスの形式が不正' => [
            ['name' => 'Test User', 'email' => 'not-an-email'],
            'email',
            'メールアドレスは、有効なメールアドレス形式で指定してください。',
        ],
        'メールアドレスが長すぎる' => [
            ['name' => 'Test User', 'email' => str_repeat('a', 244).'@example.com'],
            'email',
            'メールアドレスの文字数は、255文字以下である必要があります。',
        ],
    ]);

    it('既に使われているメールアドレスの検証文言を表示する', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(settingsUrl('profile'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $other->email,
            ]);

        $response->assertSessionHasErrors([
            'email' => '指定のメールアドレスは既に使用されています。',
        ]);
    });

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

        expect($user->fresh())->not->toBeNull()
            ->remember_token->toBe($rememberToken);
    });
});

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
            ->assertRedirect(settingsUrl('profile'));
    });

    it('画面を開く以外のメソッドは受け付けない', function () {
        $this->actingAs(User::factory()->create())
            ->post('/settings')
            ->assertMethodNotAllowed();
    });
});

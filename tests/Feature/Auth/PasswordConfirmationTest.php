<?php

declare(strict_types=1);

use App\Http\Middleware\RotateSessionOnConfirmation;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RotateSessionOnConfirmation::class);

describe('パスワード確認', function () {
    it('パスワード確認画面を表示できる', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('auth/confirm-password')
                ->where('seo.title', '本人確認')
                ->where('seo.description', '続行するには現在のパスワードを入力してください。')
        );
    });

    it('パスキーを持たないユーザーにはパスキーでの確認を案内しない', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertInertia(fn (Assert $page) => $page->where('hasPasskeys', false));
    });

    it('パスキーを持つユーザーにはパスキーでの確認を案内する', function () {
        $user = User::factory()->create(['password' => null]);
        $user->passkeys()->create([
            'name' => 'iPhone',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.hasPassword', false)
                ->where('hasPasskeys', true));
    });

    it('パスワードのない Google 連携済みのユーザーに Google での確認を案内する', function (bool $configured, ?string $googleId, bool $hasPassword, bool $expected) {
        configureGoogleOAuth($configured);
        $user = User::factory()->create(['google_id' => $googleId, ...($hasPassword ? [] : ['password' => null])]);

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertInertia(fn (Assert $page) => $page->where('canConfirmWithGoogle', $expected));
    })->with([
        'Google 連携済み' => [true, 'google-123', false, true],
        'Google 未連携' => [true, null, false, false],
        'Google ログイン未設定' => [false, 'google-123', false, false],
        'パスワードあり' => [true, 'google-123', true, false],
    ]);

    it('Google での確認に失敗した理由を表示する', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['error' => '確認に失敗しました。'])
            ->get(route('password.confirm'))
            ->assertInertia(fn (Assert $page) => $page->where('error', '確認に失敗しました。'));
    });

    it('パスワード確認には認証が必要', function () {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    });

    it('パスワードで本人確認するとログインを保ったままセッション ID を作り直す', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $sessionId = str_repeat('a', 40);
        insertDatabaseSession($sessionId, $user);

        $this->actingAs($user)
            ->withCookie(config('session.cookie'), $sessionId)
            ->post(route('password.confirm.store'), ['password' => UserFactory::DEFAULT_PASSWORD])
            ->assertSessionHas('auth.password_confirmed_at');

        // 古い ID の行を消さないと、コピーされた Cookie がそのまま使える。
        expect(app('session.store')->getId())->not->toBe($sessionId)
            ->and(databaseSessionIds())->not->toContain($sessionId);
        $this->assertAuthenticatedAs($user);
    });

    it('本人確認に失敗したときはセッション ID を変えない', function () {
        $user = User::factory()->create();
        $sessionId = str_repeat('a', 40);

        $this->actingAs($user)
            ->withCookie(config('session.cookie'), $sessionId)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionMissing('auth.password_confirmed_at');

        expect(app('session.store')->getId())->toBe($sessionId);
    });

    it('確認済みを記録した経路を問わずセッション ID を作り直す', function () {
        Route::middleware('web')->post('/_testing/confirm', function (Request $request) {
            $request->session()->passwordConfirmed();

            return response('ok');
        });
        $sessionId = str_repeat('a', 40);

        $this->actingAs(User::factory()->create())
            ->withCookie(config('session.cookie'), $sessionId)
            ->post('/_testing/confirm')
            ->assertOk();

        expect(app('session.store')->getId())->not->toBe($sessionId);
    });

    it('確認済みの記録が変わらないリクエストではセッション ID を変えない', function () {
        Route::middleware('web')->post('/_testing/no-confirm', fn () => response('ok'));
        $sessionId = str_repeat('a', 40);

        $this->actingAs(User::factory()->create())
            ->withCookie(config('session.cookie'), $sessionId)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/_testing/no-confirm')
            ->assertOk();

        expect(app('session.store')->getId())->toBe($sessionId);
    });
});

<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

    it('Google 連携済みのユーザーに Google での確認を案内する', function (bool $configured, ?string $googleId, bool $expected) {
        configureGoogleOAuth($configured);
        $user = User::factory()->create(['password' => null, 'google_id' => $googleId]);

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertInertia(fn (Assert $page) => $page->where('canConfirmWithGoogle', $expected));
    })->with([
        'Google 連携済み' => [true, 'google-123', true],
        'Google 未連携' => [true, null, false],
        'Google ログイン未設定' => [false, 'google-123', false],
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
});

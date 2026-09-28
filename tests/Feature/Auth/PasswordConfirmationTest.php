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

    it('パスワード確認には認証が必要', function () {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    });
});

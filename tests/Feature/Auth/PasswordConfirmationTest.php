<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('パスワード確認画面を表示できる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('auth/confirm-password')
            ->where('seo.title', 'パスワード確認')
            ->where('seo.description', '続行するには現在のパスワードを入力してください。')
    );
});

test('パスワード確認には認証が必要', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

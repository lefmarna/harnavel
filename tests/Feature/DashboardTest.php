<?php

use App\Models\User;

test('ゲストはログインページへリダイレクトされる', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('認証済みユーザーはダッシュボードを表示できる', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

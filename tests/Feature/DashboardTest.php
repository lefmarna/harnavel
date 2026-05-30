<?php

use App\Models\User;

describe('ダッシュボード', function () {
    it('ゲストはログインページへリダイレクトされる', function () {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    });

    it('認証済みユーザーはダッシュボードを表示できる', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    });
});

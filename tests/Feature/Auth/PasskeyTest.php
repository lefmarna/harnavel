<?php

declare(strict_types=1);

use App\Models\User;

describe('パスキー', function () {
    it('パスキー登録の候補は未認証では取得できない', function () {
        $this->get(route('passkey.registration-options'))
            ->assertRedirect(route('login'));
    });

    it('パスキーでのログイン候補は未認証でも取得できる', function () {
        $this->getJson(route('passkey.login-options'))->assertOk();
    });

    it('well-known エンドポイントが登録画面と管理画面を案内する', function () {
        $this->getJson(route('well-known.passkeys'))
            ->assertOk()
            ->assertExactJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    });

    it('パスワード確認が切れているとき登録候補は 423 を返す', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('passkey.registration-options'))
            ->assertStatus(423);
    });
});

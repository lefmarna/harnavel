<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

describe('二段階認証設定', function () {
    it('二段階認証設定画面を表示できる', function () {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.show'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('settings/two-factor')
                    ->where('twoFactorEnabled', false)
                    ->where('seo.title', '二段階認証設定')
                    ->where('seo.description', '二段階認証の設定を管理します。')
            );
    });

    it('二段階認証設定画面は有効時にパスワード確認を要求する', function () {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('two-factor.show'));

        $response->assertRedirect(route('password.confirm'));
    });

    it('二段階認証設定画面はパスワード確認が無効なら要求しない', function () {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]);

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('settings/two-factor')
            );
    });

    it('二段階認証が無効なら二段階認証設定画面はforbiddenを返す', function () {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.show'))
            ->assertForbidden();
    });
});

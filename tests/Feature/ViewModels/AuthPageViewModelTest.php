<?php

declare(strict_types=1);

use App\ViewModels\Auth\ForgotPasswordPageViewModel;
use App\ViewModels\Auth\LoginPageViewModel;
use App\ViewModels\Auth\ResetPasswordPageViewModel;
use App\ViewModels\Auth\VerifyEmailPageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

describe('Auth 専用 PageViewModel', function () {
    it('ログインページは注入したコピーを noindex の SEO として返せる', function () {
        Route::middleware('web')->get('/_spec/login-page-view-model', fn () => Inertia::render(
            'auth/login',
            new LoginPageViewModel(
                title: 'カスタムログイン',
                description: 'カスタムログインの説明。',
                canResetPassword: true,
                canRegister: true,
                canGoogleLogin: false,
                error: null,
                status: null,
            ),
        ));

        $response = $this->get('/_spec/login-page-view-model');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('seo.title', 'カスタムログイン')
                ->where('seo.description', 'カスタムログインの説明。')
                ->where('seo.robots', 'noindex, nofollow'));
    });

    it('パスワード再設定リンクページは注入したコピーを noindex の SEO として返せる', function () {
        Route::middleware('web')->get('/_spec/forgot-password-page-view-model', fn () => Inertia::render(
            'auth/forgot-password',
            new ForgotPasswordPageViewModel(
                title: 'カスタム再設定リンク',
                description: 'カスタム再設定リンクの説明。',
                status: null,
            ),
        ));

        $response = $this->get('/_spec/forgot-password-page-view-model');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/forgot-password')
                ->where('seo.title', 'カスタム再設定リンク')
                ->where('seo.description', 'カスタム再設定リンクの説明。')
                ->where('seo.robots', 'noindex, nofollow'));
    });

    it('パスワード再設定ページは注入したコピーを noindex の SEO として返せる', function () {
        Route::middleware('web')->get('/_spec/reset-password-page-view-model', fn () => Inertia::render(
            'auth/reset-password',
            new ResetPasswordPageViewModel(
                title: 'カスタムパスワード再設定',
                description: 'カスタムパスワード再設定の説明。',
                email: 'user@example.com',
                token: 'test-token',
            ),
        ));

        $response = $this->get('/_spec/reset-password-page-view-model');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/reset-password')
                ->where('seo.title', 'カスタムパスワード再設定')
                ->where('seo.description', 'カスタムパスワード再設定の説明。')
                ->where('seo.robots', 'noindex, nofollow'));
    });

    it('メール認証ページは注入したコピーを noindex の SEO として返せる', function () {
        Route::middleware('web')->get('/_spec/verify-email-page-view-model', fn () => Inertia::render(
            'auth/verify-email',
            new VerifyEmailPageViewModel(
                title: 'カスタムメール認証',
                description: 'カスタムメール認証の説明。',
                status: null,
            ),
        ));

        $response = $this->get('/_spec/verify-email-page-view-model');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/verify-email')
                ->where('seo.title', 'カスタムメール認証')
                ->where('seo.description', 'カスタムメール認証の説明。')
                ->where('seo.robots', 'noindex, nofollow'));
    });
});

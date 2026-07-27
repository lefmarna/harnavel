<?php

declare(strict_types=1);

use App\ViewModels\Auth\LoginPageViewModel;
use App\ViewModels\Auth\ResetPasswordPageViewModel;
use App\ViewModels\Auth\StatusPageViewModel;
use App\ViewModels\PageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

describe('Auth 専用 PageViewModel', function () {
    /**
     * @param  array<string, mixed>  $expectedProps
     */
    it('注入したコピーを noindex の SEO として返し固有 props を公開する', function (
        string $component,
        PageViewModel $viewModel,
        array $expectedProps,
    ) {
        Route::middleware('web')->get('/_spec/auth-page-view-model', fn () => Inertia::render($component, $viewModel));

        $this->get('/_spec/auth-page-view-model')
            ->assertOk()
            ->assertInertia(function ($page) use ($component, $expectedProps) {
                $page->component($component)
                    ->where('seo.title', 'カスタムタイトル')
                    ->where('seo.description', 'カスタム説明。')
                    ->where('seo.robots', 'noindex, nofollow');

                foreach ($expectedProps as $key => $value) {
                    $page->where($key, $value);
                }
            });
    })->with([
        'ログイン' => [
            'auth/login',
            new LoginPageViewModel(
                title: 'カスタムタイトル',
                description: 'カスタム説明。',
                canResetPassword: true,
                canRegister: false,
                canGoogleLogin: true,
                error: 'エラーです。',
                status: null,
            ),
            [
                'canResetPassword' => true,
                'canRegister' => false,
                'canGoogleLogin' => true,
                'error' => 'エラーです。',
                'status' => null,
            ],
        ],
        'パスワード再設定' => [
            'auth/reset-password',
            new ResetPasswordPageViewModel(
                title: 'カスタムタイトル',
                description: 'カスタム説明。',
                email: 'user@example.com',
                token: 'test-token',
            ),
            [
                'email' => 'user@example.com',
                'token' => 'test-token',
            ],
        ],
        'パスワード再設定リンク送信' => [
            'auth/forgot-password',
            new StatusPageViewModel(
                title: 'カスタムタイトル',
                description: 'カスタム説明。',
                status: 'リンクを送信しました。',
            ),
            ['status' => 'リンクを送信しました。'],
        ],
        'メール認証' => [
            'auth/verify-email',
            new StatusPageViewModel(
                title: 'カスタムタイトル',
                description: 'カスタム説明。',
                status: null,
            ),
            ['status' => null],
        ],
    ]);
});

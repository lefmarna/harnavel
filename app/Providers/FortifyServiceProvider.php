<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\GoogleOAuth;
use App\ViewModels\PageViewModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        $noIndexRobots = 'noindex, nofollow';

        Fortify::loginView(fn (Request $request) => Inertia::render(
            'auth/login',
            new PageViewModel(
                title: 'ログイン',
                description: 'メールアドレスとパスワードを入力してログインします。',
                props: [
                    'canResetPassword' => Features::enabled(Features::resetPasswords()),
                    'canRegister' => Features::enabled(Features::registration()),
                    'canGoogleLogin' => GoogleOAuth::isConfigured(),
                    'error' => $request->session()->get('error'),
                    'status' => $request->session()->get('status'),
                ],
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render(
            'auth/reset-password',
            new PageViewModel(
                title: 'パスワード再設定',
                description: 'アカウントの新しいパスワードを設定します。',
                props: [
                    'email' => $request->email,
                    'token' => $request->route('token'),
                ],
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render(
            'auth/forgot-password',
            new PageViewModel(
                title: 'パスワードをお忘れの方',
                description: 'メールアドレスを入力してパスワード再設定リンクを受け取ります。',
                props: [
                    'status' => $request->session()->get('status'),
                ],
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render(
            'auth/verify-email',
            new PageViewModel(
                title: 'メール認証',
                description: 'メールアドレスの認証を完了してください。',
                props: [
                    'status' => $request->session()->get('status'),
                ],
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::registerView(fn () => Inertia::render(
            'auth/register',
            new PageViewModel(
                title: '新規登録',
                description: '必要事項を入力して新しいアカウントを作成します。',
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::twoFactorChallengeView(fn () => Inertia::render(
            'auth/two-factor-challenge',
            new PageViewModel(
                title: '二段階認証',
                description: '認証アプリのコードを入力して続行します。',
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));

        Fortify::confirmPasswordView(fn () => Inertia::render(
            'auth/confirm-password',
            new PageViewModel(
                title: 'パスワード確認',
                description: '続行するには現在のパスワードを入力してください。',
                seo: [
                    'robots' => $noIndexRobots,
                ],
            )->toArray(),
        ));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $usernameInput = $request->input(Fortify::username(), '');
            $username = is_string($usernameInput) ? $usernameInput : '';
            $ip = $request->ip() ?? '';
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$ip);

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}

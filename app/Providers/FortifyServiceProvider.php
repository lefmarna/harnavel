<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\GoogleOAuth;
use App\ViewModels\Auth\ForgotPasswordPageViewModel;
use App\ViewModels\Auth\LoginPageViewModel;
use App\ViewModels\Auth\ResetPasswordPageViewModel;
use App\ViewModels\Auth\VerifyEmailPageViewModel;
use App\ViewModels\StaticPageViewModel;
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
        Fortify::loginView(fn (Request $request) => Inertia::render(
            'auth/login',
            new LoginPageViewModel(
                canResetPassword: Features::enabled(Features::resetPasswords()),
                canRegister: Features::enabled(Features::registration()),
                canGoogleLogin: GoogleOAuth::isConfigured(),
                error: $this->sessionString($request, 'error'),
                status: $this->sessionString($request, 'status'),
            ),
        ));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render(
            'auth/reset-password',
            new ResetPasswordPageViewModel(
                email: is_string($email = $request->email) ? $email : '',
                token: is_string($token = $request->route('token')) ? $token : '',
            ),
        ));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render(
            'auth/forgot-password',
            new ForgotPasswordPageViewModel(
                status: $this->sessionString($request, 'status'),
            ),
        ));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render(
            'auth/verify-email',
            new VerifyEmailPageViewModel(
                status: $this->sessionString($request, 'status'),
            ),
        ));

        Fortify::registerView(fn () => Inertia::render(
            'auth/register',
            new StaticPageViewModel(
                title: '新規登録',
                description: '必要事項を入力して新しいアカウントを作成します。',
                noIndex: true,
            ),
        ));

        Fortify::twoFactorChallengeView(fn () => Inertia::render(
            'auth/two-factor-challenge',
            new StaticPageViewModel(
                title: '二段階認証',
                description: '認証アプリのコードを入力して続行します。',
                noIndex: true,
            ),
        ));

        Fortify::confirmPasswordView(fn () => Inertia::render(
            'auth/confirm-password',
            new StaticPageViewModel(
                title: 'パスワード確認',
                description: '続行するには現在のパスワードを入力してください。',
                noIndex: true,
            ),
        ));
    }

    private function sessionString(Request $request, string $key): ?string
    {
        $value = $request->session()->get($key);

        return is_string($value) ? $value : null;
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

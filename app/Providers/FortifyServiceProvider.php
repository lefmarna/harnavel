<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\GoogleOAuth;
use App\ViewModels\Auth\LoginPageViewModel;
use App\ViewModels\Auth\ResetPasswordPageViewModel;
use App\ViewModels\Auth\StatusPageViewModel;
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
                title: __('pages.auth.login.title'),
                description: __('pages.auth.login.description'),
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
                title: __('pages.auth.reset_password.title'),
                description: __('pages.auth.reset_password.description'),
                email: is_string($email = $request->email) ? $email : '',
                token: is_string($token = $request->route('token')) ? $token : '',
            ),
        ));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render(
            'auth/forgot-password',
            new StatusPageViewModel(
                title: __('pages.auth.forgot_password.title'),
                description: __('pages.auth.forgot_password.description'),
                status: $this->sessionString($request, 'status'),
            ),
        ));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render(
            'auth/verify-email',
            new StatusPageViewModel(
                title: __('pages.auth.verify_email.title'),
                description: __('pages.auth.verify_email.description'),
                status: $this->sessionString($request, 'status'),
            ),
        ));

        Fortify::registerView(fn () => Inertia::render(
            'auth/register',
            new StaticPageViewModel(
                title: __('pages.auth.register.title'),
                description: __('pages.auth.register.description'),
                noIndex: true,
            ),
        ));

        Fortify::confirmPasswordView(fn () => Inertia::render(
            'auth/confirm-password',
            new StaticPageViewModel(
                title: __('pages.auth.confirm_password.title'),
                description: __('pages.auth.confirm_password.description'),
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
        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');
            $key = is_string($credentialId) && $credentialId !== ''
                ? $credentialId
                : $request->session()->getId();

            return Limit::perMinute(10)->by($key.'|'.($request->ip() ?? ''));
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

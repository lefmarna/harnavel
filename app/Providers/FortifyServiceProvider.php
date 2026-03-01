<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
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
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'canGoogleLogin' => config('services.google.client_id') !== null && config('services.google.client_id') !== ''
                && config('services.google.client_secret') !== null && config('services.google.client_secret') !== ''
                && config('services.google.redirect') !== null && config('services.google.redirect') !== '',
            'error' => $request->session()->get('error'),
            'status' => $request->session()->get('status'),
            'seo' => [
                'title' => 'Log in',
                'description' => 'Log in to your account.',
            ],
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'seo' => [
                'title' => 'Reset password',
                'description' => 'Set a new password for your account.',
            ],
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'seo' => [
                'title' => 'Forgot password',
                'description' => 'Enter your email to receive a password reset link.',
            ],
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'seo' => [
                'title' => 'Email verification',
            ],
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'seo' => [
                'title' => 'Register',
                'description' => 'Create a new account.',
            ],
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge', [
            'seo' => [
                'title' => 'Two-Factor Authentication',
                'description' => 'Enter your authentication code to continue.',
            ],
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password', [
            'seo' => [
                'title' => 'Confirm password',
            ],
        ]));
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

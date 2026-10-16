<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\AccountLoginThrottle;
use App\Support\GoogleOAuth;
use App\ViewModels\Auth\ConfirmPasswordPageViewModel;
use App\ViewModels\Auth\LoginPageViewModel;
use App\ViewModels\Auth\ResetPasswordPageViewModel;
use App\ViewModels\Auth\StatusPageViewModel;
use App\ViewModels\StaticPageViewModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\LogoutResponse as FortifyLogoutResponse;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;

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

        // 未登録や再送待ちを伝えると、登録済みのメールアドレスを判別できてしまう。
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            fn (): SuccessfulPasswordResetLinkRequestResponse => new SuccessfulPasswordResetLinkRequestResponse(Password::RESET_LINK_SENT),
        );

        // Fortify はセッションを作り直してから応答を作るので、履歴を消す指示は次の画面まで残る。
        $this->app->bind(LogoutResponse::class, function (): FortifyLogoutResponse {
            Inertia::clearHistory();

            return new FortifyLogoutResponse;
        });
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

        Fortify::confirmPasswordView(fn (Request $request) => Inertia::render(
            'auth/confirm-password',
            new ConfirmPasswordPageViewModel(
                title: __('pages.auth.confirm_password.title'),
                description: __('pages.auth.confirm_password.description'),
                hasPasskeys: ($user = $request->user()) instanceof User && $user->passkeys()->exists(),
                canConfirmWithGoogle: $user instanceof User && $user->canConfirmWithGoogle(),
                error: $this->sessionString($request, 'error'),
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
            $ip = $request->ip() ?? '';

            // 資格情報 ID は送る側が決めるので、接頭辞を分けて接続元のキーと重ならないようにする。
            return [
                Limit::perMinute(10)->by('credential|'.$key.'|'.$ip),
                // 資格情報 ID もセッションも送る側が作り直せるので、接続元でも数える。
                // 同じ回線の利用者をまとめて止めないよう、1分に100回ほどのログインを通せる幅にする。
                Limit::perMinute(300)->by('ip|'.$ip),
            ];
        });

        RateLimiter::for('login', function (Request $request) {
            $usernameInput = $request->input(Fortify::username(), '');
            $username = Str::transliterate(Str::lower(is_string($usernameInput) ? $usernameInput : ''));
            $ip = $request->ip() ?? '';

            return [
                Limit::perMinute(5)->by($username.'|'.$ip),
                // IP を分散させた同じアカウントへの総当たりを抑える。本人を締め出しにくいよう失敗だけを数える。
                Limit::perHour(20)->by(AccountLoginThrottle::key($username))->after(fn (): bool => ! Auth::check()),
            ];
        });

        // 1 分あたりの上限だけでは、1 時間に数百通の確認メールを送れてしまう。Fortify は届いたリンクの確認にも
        // 同じ上限を使うので、時間単位の上限は再送だけにかけ、送ったリンクを使えなくしない。
        RateLimiter::for('verification', fn (Request $request): array => [
            Limit::perMinute(6)->by('verification|'.$this->userKey($request)),
            ...$request->routeIs('verification.send')
                ? [Limit::perHour(5)->by('verification-send-hour|'.$this->userKey($request))]
                : [],
        ]);

        // Fortify のルートには個別にミドルウェアを付けられないため、ルート名で上限を選ぶ。
        RateLimiter::for('fortify', function (Request $request) {
            $ip = $request->ip() ?? '';

            return match ($request->route()?->getName()) {
                'register.store' => Limit::perHour(10)->by('register|'.$ip),
                'password.email' => Limit::perHour(10)->by('password-email|'.$ip),
                'password.update' => Limit::perMinute(10)->by('password-update|'.$ip),
                'password.confirm.store' => Limit::perMinute(5)->by('password-confirm|'.$this->userKey($request)),
                default => Limit::none(),
            };
        });
    }

    private function userKey(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? (string) $id : ($request->ip() ?? '');
    }
}

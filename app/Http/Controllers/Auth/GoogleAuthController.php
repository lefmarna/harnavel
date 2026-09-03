<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleLinkConfirmationPendingException;
use App\Http\Controllers\Controller;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use App\Support\GoogleOAuth;
use App\ViewModels\Auth\GoogleLinkVerifyPageViewModel;
use App\ViewModels\StaticPageViewModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthServiceContract $googleAuthService) {}

    public function redirect(): RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Googleログインは現在利用できません。');
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Googleログインは現在利用できません。');
        }

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            /** @var User $googleUser */
            $googleUser = $driver->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['exception_class' => $e::class]);

            return redirect()->route('login')
                ->with('error', 'Google認証に失敗しました。もう一度お試しください。');
        }

        /** @var array<string, mixed> $rawData */
        $rawData = $googleUser->getRaw();
        $emailVerified = filter_var($rawData['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $emailVerified) {
            return redirect()->route('login')
                ->with('error', 'Googleアカウントのメールアドレスが未検証です。Googleでメール認証を完了してから再度お試しください。');
        }

        $email = $googleUser->getEmail();
        if ($email === null) {
            return redirect()->route('login')
                ->with('error', 'Googleアカウントのメールアドレスが取得できませんでした。別のアカウントでお試しください。');
        }

        /** @var mixed $googleId */
        $googleId = $googleUser->getId();
        if (! is_string($googleId) || $googleId === '') {
            return redirect()->route('login')
                ->with('error', 'Googleアカウント情報の取得に失敗しました。もう一度お試しください。');
        }

        $name = $googleUser->getName();

        try {
            $user = $this->googleAuthService->findOrCreate($googleId, $email, $name);
        } catch (QueryException $e) {
            Log::warning('Google OAuth database error.', ['exception_class' => $e::class]);

            return redirect()->route('login')
                ->with('error', 'Google認証に失敗しました。もう一度お試しください。');
        } catch (GoogleLinkConfirmationPendingException) {
            return redirect()
                ->route('auth.google.link.confirm')
                ->with('google_link_email_sent', true);
        } catch (GoogleAuthException $e) {
            return redirect()->route('login')
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Google OAuth unexpected error.', ['exception_class' => $e::class]);

            return redirect()->route('login')
                ->with('error', 'Google認証に失敗しました。もう一度お試しください。');
        }

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    public function linkConfirm(): Response
    {
        if (session('google_link_email_sent') !== true) {
            abort(404);
        }

        return Inertia::render(
            'auth/google-link/confirm',
            new StaticPageViewModel(
                title: 'Googleログイン連携の確認',
                description: '確認メールからGoogleログイン連携を完了してください。',
                noIndex: true,
            ),
        );
    }

    public function linkVerify(Request $request, string $userId): Response
    {
        $linkPayload = $this->validatedGoogleLinkPayload($request, $userId);

        if ($linkPayload === null) {
            return $this->googleLinkVerifyPage('error');
        }

        return $this->googleLinkVerifyPage(
            'confirm',
            actionUrl: $request->fullUrl(),
            email: $linkPayload['email'],
        );
    }

    public function linkComplete(Request $request, string $userId): Response
    {
        $linkPayload = $this->validatedGoogleLinkPayload($request, $userId);

        if ($linkPayload === null) {
            return $this->googleLinkVerifyPage('error');
        }

        try {
            if (! $this->googleAuthService->confirmLink($userId, $linkPayload['google_id'], $linkPayload['email'])) {
                Log::warning('Google link verification could not be confirmed.', [
                    'user_id' => $userId,
                ]);

                return $this->googleLinkVerifyPage('error');
            }
        } catch (Throwable $e) {
            Log::warning('Google link verification failed.', ['exception_class' => $e::class]);

            return $this->googleLinkVerifyPage('error');
        }

        return $this->googleLinkVerifyPage('success');
    }

    /**
     * @return array{google_id: string, email: string}|null
     */
    private function validatedGoogleLinkPayload(Request $request, string $userId): ?array
    {
        $ignoredQueryParameters = $this->ignoredGoogleLinkSignatureQueryParameters($request);

        if (! URL::hasCorrectSignature($request, ignoreQuery: $ignoredQueryParameters)) {
            Log::warning('Google link verification signature is invalid.', [
                'user_id' => $userId,
                'ignored_query_parameters' => $ignoredQueryParameters,
                'query_keys' => array_keys($request->query()),
            ]);

            return null;
        }

        if (! URL::signatureHasNotExpired($request)) {
            Log::warning('Google link verification signature has expired.', [
                'user_id' => $userId,
                'expires' => $request->query('expires'),
            ]);

            return null;
        }

        $payload = $request->query('payload');

        if (! is_string($payload) || $payload === '') {
            Log::warning('Google link verification payload is missing.', [
                'user_id' => $userId,
            ]);

            return null;
        }

        try {
            $decryptedPayload = Crypt::decryptString($payload);
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($decryptedPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('Google link verification payload is invalid.', ['exception_class' => $e::class]);

            return null;
        }

        $googleId = $decoded['google_id'] ?? null;
        $email = $decoded['email'] ?? null;

        if (! is_string($googleId) || $googleId === '' || ! is_string($email) || $email === '') {
            return null;
        }

        return [
            'google_id' => $googleId,
            'email' => $email,
        ];
    }

    /**
     * @return list<string>
     */
    private function ignoredGoogleLinkSignatureQueryParameters(Request $request): array
    {
        /** @var list<string> $ignoredQueryParameters */
        $ignoredQueryParameters = collect(array_keys($request->query()))
            ->filter(fn (int|string $key): bool => is_string($key))
            ->reject(fn (string $key): bool => in_array($key, ['payload', 'expires', 'signature'], true))
            ->values()
            ->all();

        return $ignoredQueryParameters;
    }

    private function googleLinkVerifyPage(string $status, ?string $actionUrl = null, ?string $email = null): Response
    {
        return Inertia::render(
            'auth/google-link/verify',
            new GoogleLinkVerifyPageViewModel($status, $actionUrl, $email),
        );
    }
}

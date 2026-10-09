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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User;
use Throwable;

class GoogleAuthController extends Controller
{
    private const REAUTHENTICATION_SESSION_KEY = 'google_reauthentication';

    public function __construct(private readonly GoogleAuthServiceContract $googleAuthService) {}

    public function redirect(): RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('login')
                ->with('error', __('auth.google.unavailable'));
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    public function reauthenticate(Request $request): RedirectResponse
    {
        if (! $this->currentUser($request)->canConfirmWithGoogle()) {
            return redirect()->route('password.confirm')
                ->with('error', __('auth.google.reauthentication_unavailable'));
        }

        $request->session()->put(self::REAUTHENTICATION_SESSION_KEY, true);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        // 既存の Google セッションで黙って通過させず、どのアカウントで確認するかを選ばせる。
        return $driver->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return $this->completeReauthentication($request);
        }

        if (! GoogleOAuth::isConfigured()) {
            return redirect()->route('login')
                ->with('error', __('auth.google.unavailable'));
        }

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            /** @var User $googleUser */
            $googleUser = $driver->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['exception_class' => $e::class]);

            return redirect()->route('login')
                ->with('error', __('auth.google.failed'));
        }

        /** @var array<string, mixed> $rawData */
        $rawData = $googleUser->getRaw();
        $emailVerified = filter_var($rawData['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $emailVerified) {
            return redirect()->route('login')
                ->with('error', __('auth.google.email_unverified'));
        }

        $email = $googleUser->getEmail();
        if ($email === null) {
            return redirect()->route('login')
                ->with('error', __('auth.google.email_missing'));
        }

        /** @var mixed $googleId */
        $googleId = $googleUser->getId();
        if (! is_string($googleId) || $googleId === '') {
            return redirect()->route('login')
                ->with('error', __('auth.google.profile_failed'));
        }

        $name = $googleUser->getName();

        try {
            $user = $this->googleAuthService->findOrCreate($googleId, $email, $name);
        } catch (QueryException $e) {
            Log::warning('Google OAuth database error.', ['exception_class' => $e::class]);

            return redirect()->route('login')
                ->with('error', __('auth.google.failed'));
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
                ->with('error', __('auth.google.failed'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function completeReauthentication(Request $request): RedirectResponse
    {
        // 以前の guest ミドルウェアと同じく、ログイン済みユーザーをホームへ戻す。
        if ($request->session()->pull(self::REAUTHENTICATION_SESSION_KEY) !== true) {
            return redirect()->to(Config::string('fortify.home'));
        }

        $user = $this->currentUser($request);

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            /** @var User $googleUser */
            $googleUser = $driver->user();
        } catch (Throwable $e) {
            Log::warning('Google reauthentication callback failed.', ['exception_class' => $e::class]);

            return redirect()->route('password.confirm')
                ->with('error', __('auth.google.failed'));
        }

        if ($user->google_id === null || $googleUser->getId() !== $user->google_id) {
            Log::warning('Google reauthentication used a different Google account.');

            return redirect()->route('password.confirm')
                ->with('error', __('auth.google.reauthentication_mismatch'));
        }

        $request->session()->passwordConfirmed();

        Log::info('User confirmed identity with Google.', ['user_id' => $user->getKey()]);

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
                title: __('pages.auth.google_link.title'),
                description: __('pages.auth.google_link.description'),
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

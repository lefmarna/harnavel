<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuth\GoogleAuthServiceContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthServiceContract $googleAuthService) {}

    public function redirect(): RedirectResponse
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = $driver->user();
        } catch (\Exception $e) {
            Log::warning('Google OAuth callback failed.', ['exception' => $e]);

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

        $googleId = $googleUser->getId();
        if ($googleId === '') {
            return redirect()->route('login')
                ->with('error', 'Googleアカウント情報の取得に失敗しました。もう一度お試しください。');
        }

        $name = $googleUser->getName() ?? $email;

        try {
            $user = $this->googleAuthService->findOrCreate($googleId, $email, $name);
        } catch (QueryException $e) {
            Log::warning('Google OAuth database error.', ['exception' => $e]);

            return redirect()->route('login')
                ->with('error', 'Google認証に失敗しました。もう一度お試しください。');
        } catch (\RuntimeException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// 任意の呼び出しで認証済みセッションを発行する。有効化条件は config/e2e.php。
// 作ったユーザーは後始末しないので、E2E 専用データベースへ向けて実行すること。
Route::prefix('_e2e')->name('e2e.')->group(function (): void {
    Route::get('auth/login-verified', function (): RedirectResponse {
        $suffix = Str::lower(Str::random(8));
        $user = User::factory()->create([
            'email' => "e2e-verified-{$suffix}@example.test",
        ]);

        $user->markEmailAsVerified();
        Auth::login($user);

        return redirect()->route('dashboard');
    })->name('auth.login-verified');

    // dashboard の verified middleware がアプリ標準の確認誘導先へ送る。
    Route::get('auth/login-unverified', function (): RedirectResponse {
        $suffix = Str::lower(Str::random(8));
        $user = User::factory()->unverified()->create([
            'email' => "e2e-unverified-{$suffix}@example.test",
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    })->name('auth.login-unverified');

    Route::get('auth/google-link/confirm', function (Request $request): RedirectResponse {
        $request->session()->flash('google_link_email_sent', true);

        return redirect()->route('auth.google.link.confirm');
    })->name('auth.google-link.confirm');

    Route::get('auth/google-link/verify', function (): RedirectResponse {
        $suffix = Str::lower(Str::random(8));
        $user = User::factory()->unverified()->create([
            'email' => "google-link-{$suffix}@example.test",
            'google_id' => null,
        ]);
        $userId = $user->getKey();

        abort_unless(is_int($userId) || is_string($userId), 404);

        $payload = Crypt::encryptString(json_encode([
            'google_id' => "e2e-google-id-{$suffix}",
            'email' => $user->email,
        ], JSON_THROW_ON_ERROR));

        return redirect()->to(URL::temporarySignedRoute(
            'auth.google.link.verify',
            now()->addHour(),
            [
                'userId' => $userId,
                'payload' => $payload,
            ],
        ));
    })->name('auth.google-link.verify');
});

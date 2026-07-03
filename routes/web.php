<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\User;
use App\ViewModels\StaticPageViewModel;
use App\ViewModels\WelcomePageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render(
        'welcome',
        new WelcomePageViewModel(
            canRegister: Features::enabled(Features::registration()),
        ),
    );
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render(
        'dashboard',
        new StaticPageViewModel(
            title: 'ダッシュボード',
            description: 'アプリケーションの利用状況や設定への導線を確認できます。',
        ),
    );
})->middleware(['auth', 'verified'])->name('dashboard');

// Google OAuth認証ルート（ゲスト専用）
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::prefix('auth/google/link')->name('auth.google.link.')->controller(GoogleAuthController::class)->group(function () {
    Route::get('/confirm', 'linkConfirm')->name('confirm');
    Route::get('/verify/{userId}', 'linkVerify')->name('verify');
    Route::post('/verify/{userId}', 'linkComplete')->name('complete');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/_e2e/auth/google-link/confirm', function (Request $request): RedirectResponse {
        $request->session()->flash('google_link_email_sent', true);

        return redirect()->route('auth.google.link.confirm');
    });

    Route::get('/_e2e/auth/google-link/verify', function (): RedirectResponse {
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
    });
}

require __DIR__.'/settings.php';

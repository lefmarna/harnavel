<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleAuthController;
use App\ViewModels\StaticPageViewModel;
use App\ViewModels\WelcomePageViewModel;
use Illuminate\Support\Facades\Route;
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
            title: __('pages.dashboard.title'),
            description: __('pages.dashboard.description'),
        ),
    );
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
});

Route::get('auth/google/reauthenticate', [GoogleAuthController::class, 'reauthenticate'])
    ->middleware('auth')
    ->name('auth.google.reauthenticate');

// Google 側に登録したリダイレクト URI を増やさないよう、本人確認でもログインと同じ URI へ戻る。
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('auth.google.callback');

Route::prefix('auth/google/link')->name('auth.google.link.')->controller(GoogleAuthController::class)->group(function () {
    Route::get('/confirm', 'linkConfirm')->name('confirm');
    Route::get('/verify/{userId}', 'linkVerify')->name('verify');
    Route::post('/verify/{userId}', 'linkComplete')->middleware('throttle:10,1')->name('complete');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

require __DIR__.'/settings.php';

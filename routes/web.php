<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'seo' => [
            'title' => 'Welcome',
            'description' => config('app.description', ''),
        ],
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard', [
        'seo' => [
            'title' => 'Dashboard',
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Google OAuth認証ルート（ゲスト専用）
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

require __DIR__.'/settings.php';

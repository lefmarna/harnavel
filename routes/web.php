<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\ViewModels\PageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    $appDescription = config('app.description');

    return Inertia::render(
        'welcome',
        new PageViewModel(
            title: 'ホーム',
            description: is_string($appDescription) ? $appDescription : '',
            props: [
                'canRegister' => Features::enabled(Features::registration()),
            ],
        )->toArray(),
    );
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render(
        'dashboard',
        new PageViewModel(
            title: 'ダッシュボード',
            description: 'アプリケーションの利用状況や設定への導線を確認できます。',
        )->toArray(),
    );
})->middleware(['auth', 'verified'])->name('dashboard');

// Google OAuth認証ルート（ゲスト専用）
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

require __DIR__.'/settings.php';

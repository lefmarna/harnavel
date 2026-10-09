<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\ViewModels\StaticPageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    // 転送が要るのは画面を開くときだけ。Route::redirect は全メソッドを受け、Wayfinder の型にない QUERY まで生成されてしまう。
    Route::get('settings', fn () => redirect()->route('profile.edit'));

    // メールアドレスを書き換えられると、再設定メール経由でアカウントを奪われるため本人確認を求める。
    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('password.confirm')
        ->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('password.confirm')
        ->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['password.confirm', 'throttle:6,1'])
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render(
            'settings/appearance',
            new StaticPageViewModel(
                title: __('pages.settings.appearance.title'),
                description: __('pages.settings.appearance.description'),
            ),
        );
    })->name('appearance.edit');
});

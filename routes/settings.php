<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\ViewModels\StaticPageViewModel;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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

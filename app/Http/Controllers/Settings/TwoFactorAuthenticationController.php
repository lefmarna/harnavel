<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\ViewModels\Settings\TwoFactorAuthenticationPageViewModel;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorAuthenticationController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? [new Middleware('password.confirm', only: ['show'])]
            : [];
    }

    /**
     * Show the user's two-factor authentication settings page.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render(
            'settings/two-factor',
            new TwoFactorAuthenticationPageViewModel(
                twoFactorEnabled: $user->hasEnabledTwoFactorAuthentication(),
                requiresConfirmation: Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            ),
        );
    }
}

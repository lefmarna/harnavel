<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as BaseAuthenticatedSessionController;

/**
 * Fortify の logout() を使わない理由は TADR-0016。
 */
final class AuthenticatedSessionController extends BaseAuthenticatedSessionController
{
    public function destroy(Request $request): LogoutResponse
    {
        /** @var SessionGuard $guard */
        $guard = $this->guard;
        $guard->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}

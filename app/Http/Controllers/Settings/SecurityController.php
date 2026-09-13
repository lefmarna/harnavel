<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\ViewModels\Settings\SecurityPageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $canManagePasskeys = Features::canManagePasskeys();

        return Inertia::render(
            'settings/security',
            new SecurityPageViewModel(
                canManagePasskeys: $canManagePasskeys,
                passkeys: $canManagePasskeys ? $this->passkeysOf($user) : [],
            ),
        );
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        /** @var array{password: string} $validated */
        $validated = $request->validated();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }

    /**
     * @return list<array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>
     */
    private function passkeysOf(User $user): array
    {
        return array_values($user->passkeys()
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey): array => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at?->diffForHumans() ?? '',
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->all());
    }
}

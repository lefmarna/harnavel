<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\ViewModels\Settings\ProfilePageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $this->currentUser($request);
        $status = $request->session()->get('status');

        return Inertia::render(
            'settings/profile',
            new ProfilePageViewModel(
                mustVerifyEmail: true,
                status: is_string($status) ? $status : null,
            ),
        );
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        /** @var array{name: string, email: string} $validated */
        $validated = $request->validated();
        $originalEmail = $user->email;

        $user->fill($validated);

        $emailChanged = config('fortify.lowercase_usernames')
            ? $user->isDirty('email')
            : Str::lower($originalEmail) !== Str::lower($user->email);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}

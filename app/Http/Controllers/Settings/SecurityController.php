<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\Account\SecurityNotifier;
use App\Support\RememberCookie;
use App\ViewModels\Settings\SecurityPageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class SecurityController extends Controller
{
    public function __construct(
        private readonly UserRepositoryContract $userRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $this->currentUser($request);

        $canManagePasskeys = Features::canManagePasskeys();

        return Inertia::render(
            'settings/security',
            new SecurityPageViewModel(
                canManagePasskeys: $canManagePasskeys,
                canRegisterPasskey: $user->hasVerifiedEmail(),
                passkeys: $canManagePasskeys ? $this->passkeysOf($user) : [],
                googleLinked: $user->google_id !== null,
                canUnlinkGoogle: $user->google_id !== null && $user->password !== null,
            ),
        );
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $rememberToken = $user->getRememberToken();

        /** @var array{password: string} $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = $this->lockCurrentUser($user);

            $lockedUser->password = Hash::make($validated['password']);
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);
            Password::broker(config()->string('fortify.passwords'))->deleteToken($lockedUser);

            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        });

        $this->keepCurrentSession($request, $user, $rememberToken);

        Log::info('User password was changed.', ['user_id' => $user->getKey()]);
        $this->securityNotifier->notify($user, 'password_changed');

        return back();
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $rememberToken = $user->getRememberToken();

        DB::transaction(function () use ($user): void {
            $lockedUser = $this->lockCurrentUser($user);

            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        });

        $this->keepCurrentSession($request, $user, $rememberToken);

        Log::info('Other sessions were logged out.', ['user_id' => $user->getKey()]);

        return back()->with('status', 'other-sessions-logged-out');
    }

    /**
     * 第三者がその Google アカウントで先にログインしているかもしれないので、他の端末のセッションも失効させる（TADR-0016）。
     */
    public function destroyGoogle(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $rememberToken = $user->getRememberToken();

        $unlinked = DB::transaction(function () use ($user): bool {
            $lockedUser = $this->lockCurrentUser($user);

            if ($lockedUser->password === null) {
                return false;
            }

            $lockedUser->google_id = null;
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);

            return true;
        });

        if (! $unlinked) {
            return back()->withErrors(['google' => __('form.google_unlink.password_required')]);
        }

        $this->keepCurrentSession($request, $user, $rememberToken);

        Log::info('Google account was unlinked.', ['user_id' => $user->getKey()]);

        return back()->with('status', 'google-unlinked');
    }

    /**
     * 本人の Cookie を盗んだ第三者も同じセッション ID を持つため、ログインを保ったまま ID を作り直す。
     */
    private function keepCurrentSession(Request $request, User $user, ?string $rememberToken): void
    {
        $request->session()->migrate(destroy: true);
        $user->recordSessionGeneration($request->session());
        RememberCookie::reissue($user, $rememberToken);
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Services\Account\SecurityNotifier;
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
                canUnlinkGoogle: $user->google_id !== null && $this->hasOtherCredential($user),
            ),
        );
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        /** @var array{password: string} $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            // リクエストの最初に読んだ世代から進めると、途中の再設定で進んだ世代を巻き戻すため、ロックした行から進める。
            $lockedUser = $this->lockCurrentUser($user);

            $lockedUser->password = Hash::make($validated['password']);
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);
            Password::broker(config()->string('fortify.passwords'))->deleteToken($lockedUser);

            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        });

        // 本人の Cookie を盗んだ第三者も同じセッション ID を持つため、ログインを保ったまま ID を作り直す。
        $request->session()->migrate(destroy: true);
        $user->recordSessionGeneration($request->session());

        Log::info('User password was changed.', ['user_id' => $user->getKey()]);
        $this->securityNotifier->notify($user, 'password_changed');

        return back();
    }

    /**
     * 第三者がその Google アカウントで先にログインしているかもしれないので、他の端末のセッションも失効させる（TADR-0016）。
     */
    public function destroyGoogle(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        $unlinked = DB::transaction(function () use ($user): bool {
            $lockedUser = $this->lockCurrentUser($user);

            if (! $this->hasOtherCredential($lockedUser)) {
                return false;
            }

            $lockedUser->google_id = null;
            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);

            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);

            return true;
        });

        if (! $unlinked) {
            return back()->withErrors(['google' => __('form.google_unlink.no_other_credential')]);
        }

        $request->session()->migrate(destroy: true);
        $user->recordSessionGeneration($request->session());

        Log::info('Google account was unlinked.', ['user_id' => $user->getKey()]);

        return back()->with('status', 'google-unlinked');
    }

    private function hasOtherCredential(User $user): bool
    {
        return $user->password !== null || $user->passkeys()->exists();
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

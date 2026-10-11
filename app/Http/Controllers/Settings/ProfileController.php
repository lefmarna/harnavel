<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\Account\EmailChangeService;
use App\ViewModels\Settings\ProfilePageViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly EmailChangeService $emailChangeService) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
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
     *
     * メールアドレスは新しいアドレスの確認を待って切り替える（TADR-0016）。
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        /** @var array{name: string, email: string} $validated */
        $validated = $request->validated();

        if (! $this->emailChangeService->sameAddress($user->email, $validated['email'])
            && ! $this->emailChangeService->canChangeEmail($user)) {
            return to_route('profile.edit')->withErrors(['email' => __('form.profile_update.email_change_too_soon')]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = $this->lockCurrentUser($user);
            $lockedUser->name = $validated['name'];

            // 大文字小文字だけの違いは同じアドレスなので、確認を待たずに反映する。判定はロックした行で行い、
            // 途中で切り替わったアドレスを書き戻さない。
            if ($this->emailChangeService->sameAddress($lockedUser->email, $validated['email'])) {
                $lockedUser->email = $validated['email'];
            }

            $lockedUser->save();
            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);
        });

        if ($this->emailChangeService->sameAddress($user->email, $validated['email'])) {
            return to_route('profile.edit');
        }

        try {
            $this->emailChangeService->request($user, $validated['email']);
        } catch (Throwable $e) {
            report($e);

            return to_route('profile.edit')->withErrors(['email' => __('form.profile_update.email_change_failed')]);
        }

        return to_route('profile.edit')->with('status', 'email-change-link-sent');
    }

    /**
     * Delete the user's profile.
     *
     * 本人確認は password.confirm に任せ、パスワードを重ねて求めない（TADR-0016）。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        // ログアウトは remember_token を作り直すので、失効の判定より後に行う。
        DB::transaction(fn () => $this->lockCurrentUser($user)->delete());

        Auth::logout();

        Log::info('User account was deleted.', ['user_id' => $user->getKey()]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

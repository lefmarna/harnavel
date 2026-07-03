<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use App\Params\UpdatePasswordParams;
use App\ViewModels\StaticPageViewModel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render(
            'settings/password',
            new StaticPageViewModel(
                title: 'パスワード設定',
                description: 'アカウントのパスワードを更新します。',
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
        $params = UpdatePasswordParams::fromInput($validated);

        $user->update($params->toArray());

        return back();
    }
}

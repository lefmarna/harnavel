<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function currentUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    /**
     * トランザクションの中で呼ぶ。失効より前に始まった変更を、失効の後に書き込ませない（TADR-0016）。
     */
    protected function lockCurrentUser(User $user): User
    {
        /** @var int|string $key */
        $key = $user->getKey();

        $lockedUser = app(UserRepositoryContract::class)->find($key, lock: true);

        if ($lockedUser === null || $lockedUser->session_generation !== $user->session_generation) {
            abort(409);
        }

        return $lockedUser;
    }
}

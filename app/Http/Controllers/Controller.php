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
     * リクエストのユーザーをロックして読み直す。トランザクションの中で呼ぶ。
     * リクエストの途中でセッションを失効させていれば、失効前に始まった変更を書き込ませず 409 を返す（TADR-0016）。
     */
    protected function lockCurrentUser(User $user): User
    {
        /** @var int|string $key Eloquent::getKey() は mixed を返す。 */
        $key = $user->getKey();

        $lockedUser = app(UserRepositoryContract::class)->find($key, lock: true);

        if ($lockedUser === null || $lockedUser->session_generation !== $user->session_generation) {
            abort(409);
        }

        return $lockedUser;
    }
}

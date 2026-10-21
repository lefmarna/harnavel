<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\RememberCookie;
use Illuminate\Contracts\Session\Session;

class SessionRevocation
{
    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    /**
     * トランザクションの中で呼ぶ。失効より前に始まった変更を、失効の後に書き込ませない（TADR-0016）。
     */
    public function lockUnrevoked(User $user): User
    {
        /** @var int|string $key */
        $key = $user->getKey();

        $lockedUser = $this->userRepository->find($key, lock: true);

        if ($lockedUser === null || $lockedUser->session_generation !== $user->session_generation) {
            abort(409);
        }

        return $lockedUser;
    }

    /**
     * 失効を保存した後に呼ぶ。本人の Cookie を盗んだ第三者も同じセッション ID を持つため、
     * ログインを保ったまま ID を作り直す。
     */
    public function keepCurrentSession(Session $session, User $user, ?string $rememberToken): void
    {
        $session->migrate(destroy: true);
        $user->recordSessionGeneration($session);
        RememberCookie::reissue($user, $rememberToken);
    }
}

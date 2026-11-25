<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\RememberCookie;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

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
     * @param  (callable(User): (bool|void))|null  $mutate
     * @param  (callable(): void)|null  $afterCommit
     */
    public function revokeOtherSessions(
        Session $session,
        User $user,
        ?callable $mutate = null,
        ?callable $afterCommit = null,
    ): bool {
        $rememberToken = $user->getRememberToken();

        $revoked = DB::transaction(function () use ($user, $mutate): bool {
            $lockedUser = $this->lockUnrevoked($user);

            if ($mutate !== null && $mutate($lockedUser) === false) {
                return false;
            }

            $lockedUser->revokeSessions();
            $this->userRepository->save($lockedUser);
            $user->setRawAttributes($lockedUser->getAttributes(), sync: true);

            return true;
        });

        if (! $revoked) {
            return false;
        }

        if ($afterCommit !== null) {
            $afterCommit();
        }

        $this->keepCurrentSession($session, $user, $rememberToken);

        return true;
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

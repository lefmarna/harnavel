<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class RememberCookie
{
    /**
     * 失効で remember_token を作り直した後も、操作した端末のログイン保持を続ける（TADR-0016）。
     * 出し直すのは、作り直す前の値を持つ Cookie、つまりその時点で使えた保持だけ。SessionGuard は Cookie を
     * 出し直す公開メソッドを持たないので、その中から呼ぶ。
     */
    public static function reissue(User $user, ?string $previousToken): void
    {
        (function () use ($user, $previousToken): void {
            $recaller = $this->recaller();

            if ($previousToken !== null && $recaller !== null && hash_equals($previousToken, $recaller->token())) {
                $this->queueRecallerCookie($user);
            }
        })->call(Auth::guard());
    }
}

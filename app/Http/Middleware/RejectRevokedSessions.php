<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticateSession を使わない理由は TADR-0016。auth の前に並べ、失効したセッションはゲストとして
 * 扱うので、メールの確認リンクのようにログインが要らない画面はそのまま開ける。
 */
final class RejectRevokedSessions
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isCurrentSessionGeneration($request->session())) {
            Log::info('Revoked session was rejected.', ['user_id' => $user->getKey()]);

            // logout() は remember_token を作り直し、失効の後にログインした本人の端末まで外してしまう。
            /** @var SessionGuard $guard */
            $guard = Auth::guard();
            $guard->logoutCurrentDevice();
            // 続けて表示する画面からの送信が CSRF で弾かれないよう、トークンも作り直す。
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Inertia::clearHistory();
        }

        return $next($request);
    }
}

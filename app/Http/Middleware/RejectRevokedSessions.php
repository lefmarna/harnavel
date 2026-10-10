<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * パスワードの変更や再設定で失効させた世代のセッションをログアウトさせる（TADR-0016）。
 * Laravel の AuthenticateSession はパスワードのないユーザーを確かめず、比べる値を持たないセッションでは
 * そのときの値を記録して通すため、失効と同時に作られたセッションを残す。
 * AuthenticatesSessions を実装し、AuthenticateSession と同じく auth の後に並べる。
 */
final class RejectRevokedSessions implements AuthenticatesSessions
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
            $request->session()->flush();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 本人確認が済んだらセッション ID を作り直し、盗まれた Cookie に確認済みの状態を共有させない。
 * パスワード（Fortify）、パスキー、Google のどの経路も確認済みの記録だけは共通なので、ここで一括して扱う。
 */
final class RotateSessionOnConfirmation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        $response = $next($request);

        if ($request->session()->get('auth.password_confirmed_at') !== $confirmedAt) {
            $request->session()->migrate(destroy: true);
        }

        return $response;
    }
}

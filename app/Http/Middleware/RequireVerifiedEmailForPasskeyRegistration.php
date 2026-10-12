<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 理由は TADR-0016。Fortify のルートには個別にミドルウェアを付けられないため、ルート名で対象を選ぶ。
 * fortify.middleware は auth より外側で動くので、未ログインなら auth に任せてログイン画面へ送らせる。
 */
final class RequireVerifiedEmailForPasskeyRegistration
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || ! $request->routeIs('passkey.registration-options', 'passkey.store')) {
            return $next($request);
        }

        // 宣言は null を含むが、未確認なら abort か転送、確認済みなら $next の応答を返す。
        /** @var Response $response */
        $response = (new EnsureEmailIsVerified)->handle($request, $next);

        return $response;
    }
}

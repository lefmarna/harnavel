<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * メール未確認のアカウントは、メールの持ち主でない第三者が先に作った可能性がある。
 * その第三者に、持ち主の回復後も使えるパスキーを登録させない（TADR-0016）。
 * Fortify のルートには個別にミドルウェアを付けられないため、ルート名で対象を選ぶ。
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

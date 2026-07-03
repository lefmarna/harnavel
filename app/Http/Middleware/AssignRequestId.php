<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);

        // user_id はこのミドルウェアが StartSession より前に実行されるため
        // ここでは解決できず、null へのリセットのみ行う。実際の値は
        // Authenticated イベント経由で共有する（ShareAuthenticatedUserLogContext を参照）
        Log::shareContext([
            'request_id' => $requestId,
            'http_method' => $request->method(),
            'url' => $request->url(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => null,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SensitiveUrl;
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

        // StartSession より前に動くため user_id はここで解決できない。値は ShareAuthenticatedUserLogContext が共有する。
        Log::shareContext([
            'request_id' => $requestId,
            'http_method' => $request->method(),
            'url' => SensitiveUrl::redact($request),
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

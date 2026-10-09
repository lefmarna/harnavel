<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SensitiveUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var array<string, string> $headers */
        $headers = require __DIR__.'/../../../bootstrap/security-headers.php';

        // HSTS はポートを区別しないため、local では同じホストの HTTP 開発サービスを使えるようにする。
        if (! app()->environment('local') && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        // 秘密を含む画面から移った先の document.referrer 経由で、トークンを計測へ渡さない。
        if (SensitiveUrl::carriesSecret($request)) {
            $headers['Referrer-Policy'] = 'no-referrer';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SensitiveUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        /** @var array{'Content-Security-Policy': string, 'X-Content-Type-Options': string, 'Referrer-Policy': string, 'X-Frame-Options': string} $headers */
        $headers = require __DIR__.'/../../../bootstrap/security-headers.php';

        // デバッグ画面は nonce のないスクリプトと eval で動き、Inertia が表示する iframe も元の画面の
        // CSP を継承するので、デバッグ時は違反を報告するだけにする（TADR-0012）。
        if (config('app.debug')) {
            $headers['Content-Security-Policy-Report-Only'] = $this->scriptSrc($nonce);
        } else {
            $headers['Content-Security-Policy'] .= '; '.$this->scriptSrc($nonce);
        }

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

    private function scriptSrc(string $nonce): string
    {
        // 'unsafe-inline' と https: は strict-dynamic に対応しないブラウザ向けの予備で、対応するブラウザは無視する。
        $scriptSrc = "script-src 'nonce-{$nonce}' 'strict-dynamic' https: 'unsafe-inline'";

        // AdSense が対応する CSP は eval を許すものだけなので、使うアプリでだけ許す。
        $adsensePublisherId = config('services.google.adsense_publisher_id');

        return is_string($adsensePublisherId) && $adsensePublisherId !== ''
            ? $scriptSrc." 'unsafe-eval'"
            : $scriptSrc;
    }
}

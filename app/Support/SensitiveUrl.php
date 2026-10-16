<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * 外部の計測、ログ、通知へ秘密を流さないための判定と伏せ字（TADR-0016）。
 */
final class SensitiveUrl
{
    /**
     * password.reset の token と verification.verify の hash。
     */
    private const SECRET_ROUTE_PARAMETERS = ['token', 'hash'];

    public static function carriesSecret(Request $request): bool
    {
        // Google ログインのコールバックは、クエリに認可コードと state を持つ。
        return $request->query->has('signature')
            || $request->routeIs('auth.google.callback')
            || self::secretParameters($request) !== [];
    }

    /**
     * Request::url() と同じ形で、パス中の秘密をパラメーター名に置き換える。
     */
    public static function redact(Request $request): string
    {
        $secrets = self::secretParameters($request);

        // 同じ値でもパーセントエンコードの表記は一通りではないため、デコードしたパス要素で比べる。
        $segments = array_map(function (string $segment) use ($secrets): string {
            $name = array_search(rawurldecode($segment), $secrets, true);

            return is_string($name) ? '{'.$name.'}' : $segment;
        }, explode('/', $request->getPathInfo()));

        return rtrim($request->root().implode('/', $segments), '/');
    }

    /**
     * @return array<string, string>
     */
    private static function secretParameters(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return [];
        }

        $secrets = [];

        foreach (self::SECRET_ROUTE_PARAMETERS as $name) {
            // 未バインドのルートでは parameter() が例外を投げるため、先に有無を確かめる。
            if (! $route->hasParameter($name)) {
                continue;
            }

            $value = $route->parameter($name);

            if (is_string($value)) {
                $secrets[$name] = $value;
            }
        }

        return $secrets;
    }
}

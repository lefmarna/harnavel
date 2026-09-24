<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CheckAllowedOrigins は設定値を parse_url で正規化するため、画面にも同じ表記を渡す。
 */
final class PasskeyBrowserOrigins
{
    private const array DEFAULT_PORTS = [
        'https' => 443,
        'http' => 80,
    ];

    /**
     * @param  list<string>  $allowedOrigins
     * @return list<string>
     */
    public static function from(array $allowedOrigins): array
    {
        $origins = [];

        foreach ($allowedOrigins as $allowedOrigin) {
            $parsed = parse_url($allowedOrigin);

            if ($parsed === false) {
                return [];
            }

            if (isset($parsed['scheme'], $parsed['host'])) {
                $origins[] = self::buildOrigin($parsed['scheme'], $parsed['host'], $parsed['port'] ?? null);
            } elseif (isset($parsed['scheme'])) {
                $origins[] = $allowedOrigin;
            } else {
                $origins[] = self::buildOrigin('https', $parsed['host'] ?? $allowedOrigin, null);
            }
        }

        return array_values(array_unique($origins));
    }

    private static function buildOrigin(string $scheme, string $host, ?int $port): string
    {
        if ($port === null || (self::DEFAULT_PORTS[$scheme] ?? null) === $port) {
            return sprintf('%s://%s', $scheme, $host);
        }

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }
}

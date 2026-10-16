<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * メールで送る確認リンク。宛先や対象の値は暗号化してクエリへ載せ、URL から読めないようにする。
 * 署名付き URL なので、計測・ログ・通知からは SensitiveUrl が除く（TADR-0016）。
 */
final class SignedPayloadLink
{
    /**
     * @param  array<string, string>  $payload
     */
    public static function make(string $route, DateTimeInterface $expiration, int|string $userId, array $payload): string
    {
        return URL::temporarySignedRoute($route, $expiration, [
            'userId' => $userId,
            'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>|null
     */
    public static function read(Request $request, array $keys): ?array
    {
        // メールの計測などで付くクエリは署名の対象外として扱う。payload と expires は書き換えられないよう署名で守る。
        // Laravel は表記のままのキーで除外を判定する。変換後の名前を挙げれば、%65xpires のように表記を変えて
        // expires を上書きする追加は除外されず、署名が合わなくなる。
        $ignoreQuery = array_diff(array_keys($request->query()), ['payload', 'expires']);

        if (! URL::hasValidSignature($request, ignoreQuery: $ignoreQuery)) {
            Log::warning('Signed link is invalid or expired.', ['path' => $request->path()]);

            return null;
        }

        try {
            /** @var mixed $decoded */
            // POST 本文の値は署名されていないので、クエリから読む。
            $decoded = json_decode(Crypt::decryptString($request->query->getString('payload')), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('Signed link payload is invalid.', [
                'path' => $request->path(),
                'exception_class' => $e::class,
            ]);

            return null;
        }

        $values = [];

        foreach ($keys as $key) {
            $value = is_array($decoded) ? ($decoded[$key] ?? null) : null;

            if (! is_string($value) || $value === '') {
                return null;
            }

            $values[$key] = $value;
        }

        return $values;
    }
}

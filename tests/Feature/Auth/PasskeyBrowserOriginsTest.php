<?php

declare(strict_types=1);

use App\Support\PasskeyBrowserOrigins;
use Webauthn\CeremonyStep\CheckAllowedOrigins;

mutates(PasskeyBrowserOrigins::class);

/**
 * @param  list<string>  $allowedOrigins
 * @return list<string>
 */
function webauthnMatchedOrigins(array $allowedOrigins): array
{
    $check = new CheckAllowedOrigins($allowedOrigins);
    $read = fn (string $property): array => (new ReflectionProperty(CheckAllowedOrigins::class, $property))->getValue($check);

    $origins = [...$read('fullOrigins'), ...$read('rawOrigins')];
    sort($origins);

    return $origins;
}

it('サーバーが照合に使う origin と同じ一覧を返す', function (array $allowedOrigins) {
    $origins = PasskeyBrowserOrigins::from($allowedOrigins);
    sort($origins);

    expect($origins)->toBe(webauthnMatchedOrigins($allowedOrigins));
})->with([
    '完全な origin' => [['https://example.test']],
    '末尾のスラッシュ' => [['https://example.test/']],
    'パスとクエリ' => [['https://example.test/app?x=1#y']],
    'https の既定ポート' => [['https://example.test:443']],
    'http の既定ポート' => [['http://localhost:80']],
    '既定以外のポート' => [['http://localhost:8000']],
    '大文字を含むホストと scheme' => [['HTTPS://Example.test:443']],
    'scheme のないホスト' => [['example.test']],
    'scheme のないホストとポート' => [['example.test:8443']],
    'scheme のないホストとポートとパス' => [['example.test:8443/app']],
    'scheme がなくパスを含む値' => [['example.test/']],
    'scheme のないホストとポートとクエリ' => [['example.test:8443?x']],
    'scheme のないホストとポートとフラグメント' => [['example.test:8443#x']],
    'scheme 相対の URL' => [['//example.test']],
    'Android のアプリ識別子' => [['android:apk-key-hash:abc']],
    '重複する表記' => [['https://example.test', 'https://example.test/', 'example.test']],
]);

it('重複を除いたあとも添字の詰まった配列を返す', function () {
    expect(PasskeyBrowserOrigins::from(['https://example.test', 'https://example.test/', 'http://localhost:8000']))
        ->toBe(['https://example.test', 'http://localhost:8000']);
});

it('解釈できない設定値が1つでもあればどの origin も返さない', function () {
    expect(fn () => new CheckAllowedOrigins(['https://example.test', 'https://example.test:99999']))
        ->toThrow(InvalidArgumentException::class);

    expect(PasskeyBrowserOrigins::from(['https://example.test', 'https://example.test:99999']))
        ->toBe([]);
});

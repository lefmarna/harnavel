<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| HTTP Fakes
|--------------------------------------------------------------------------
|
| Password::defaults() の uncompromised() は HaveIBeenPwned API へ実 HTTP を飛ばす。
| Feature テストでは既定で「未漏洩」応答にスタブし、オフライン・決定的に保つ。
| 漏洩あり/なしを出し分ける PasswordPolicyTest は各テスト内で HTTP fake をリセットしている。
|
*/

uses()
    ->beforeEach(function () {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  list<string>  $keys
 */
function assertInertiaShape(AssertableInertia $page, string $component, array $keys): AssertableInertia
{
    $page->component($component);

    foreach ($keys as $key) {
        $page->has($key);
    }

    return $page;
}

/**
 * リポジトリ直下の設定ファイルから、指定プレフィックスで始まる行を trim して返す。
 *
 * 「設定ファイルにこの一行が残っていること」を固定する制約テスト用。
 */
function settingLine(string $relativePath, string $prefix): ?string
{
    $contents = file_get_contents(base_path($relativePath));

    expect($contents)->not->toBeFalse();

    return collect(explode("\n", (string) $contents))
        ->map(fn (string $line): string => trim($line))
        ->first(fn (string $line): bool => str_starts_with($line, $prefix));
}

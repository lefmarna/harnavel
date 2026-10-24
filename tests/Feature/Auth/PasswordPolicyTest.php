<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * tests/Pest.php の既定の HTTP fake が先に登録されているため、$register で Factory を
 * 差し替えてから HaveIBeenPwned の fake を登録する。
 */
describe('パスワードポリシー', function () {
    /**
     * @return TestResponse
     */
    $register = function (string $password, bool $breached = false) {
        $suffix = substr(strtoupper(sha1($password)), 5);

        Http::swap(new HttpFactory);
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response($breached ? $suffix.':42' : ''),
        ]);

        return test()->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'policy@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    };

    it('規則に合わないパスワードは拒否される', function (string $password, bool $breached = false) use ($register) {
        $register($password, $breached)
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    })->with([
        '8文字未満' => ['abc1234'],
        '64文字を超える' => [str_repeat('a', 60).'12345'],
        '日本語など非ASCII文字を含む' => ['abcd1234あ'],
        '空白を含む' => ['abcd 1234'],
        '末尾にLFを含む' => ["abcd1234\n"],
        '末尾にCRLFを含む' => ["abcd1234\r\n"],
        '漏洩済み' => ['compromised-pass1', true],
    ]);

    it('文字種の組み合わせは求めず、8文字から64文字まで受け付ける', function (string $password) use ($register) {
        $register($password)
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    })->with([
        '英字のみ' => 'onlyletters',
        '数字のみ' => '20261015',
        '記号のみ' => '!#$%&()*',
        '64文字' => str_repeat('a', 56).'12345678',
    ]);

    it('新規登録の検証文言には共通の属性名とパスワード規則の説明を使う', function (string $locale, string $password, string $message) use ($register) {
        app()->setLocale($locale);

        $register($password)
            ->assertSessionHasErrors([
                'password' => $message,
            ]);
    })->with([
        '日本語の属性名' => ['ja', 'abc1234', 'パスワードの文字数は、8文字以上である必要があります。'],
        '英語の属性名' => ['en', 'abc1234', 'The password field must be at least 8 characters.'],
        '日本語の文字種' => ['ja', 'abcd1234あ', 'パスワードは半角の英字・数字・記号のみ使用できます。'],
        '英語の文字種' => ['en', 'abcd1234あ', 'The password may only contain single-byte letters, numbers, and symbols.'],
    ]);
});

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

    it('8文字未満は拒否される', function () use ($register) {
        $register('abc1234')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('64文字を超えると拒否される', function () use ($register) {
        $register(str_repeat('a', 60).'12345')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('文字種の組み合わせは求めない', function (string $password) use ($register) {
        $register($password)
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    })->with([
        '英字のみ' => 'onlyletters',
        '数字のみ' => '20261015',
        '記号のみ' => '!#$%&()*',
    ]);

    it('日本語など非ASCII文字を含むと拒否される', function () use ($register) {
        $register('abcd1234あ')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('空白を含むと拒否される', function () use ($register) {
        $register('abcd 1234')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('漏洩済みパスワードは拒否される', function () use ($register) {
        $register('compromised-pass1', breached: true)
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('新規登録の検証文言には共通の属性名を使う', function () use ($register) {
        $register('abc1234')
            ->assertSessionHasErrors([
                'password' => 'パスワードの文字数は、8文字以上である必要があります。',
            ]);
    });

    it('ポリシーを満たすパスワードは受け付けられる', function () use ($register) {
        $register('valid-password1')
            ->assertSessionHasNoErrors()
            ->assertRedirect(config('fortify.home'));
        $this->assertAuthenticated();
    });
});

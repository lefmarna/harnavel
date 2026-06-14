<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Password::defaults()（AppServiceProvider）で定義したパスワードポリシーの仕様。
 * 登録エンドポイント経由で各ルールの境界を検証する。
 *
 * uncompromised() は HaveIBeenPwned API を引くため、$register 内で毎回 HTTP fake を
 * 設定する（既定は「未漏洩」、$breached=true で当該パスワードを「漏洩あり」に出し分け）。
 * tests/Pest.php の Feature 既定 fake は先に登録済みなので、この仕様テストでは
 * Factory を差し替えてから明示的な fake だけを登録する。
 */
describe('パスワードポリシー', function () {
    /**
     * @param  string  $password  検証対象のパスワード
     * @param  bool  $breached  HaveIBeenPwned が漏洩ありを返す状態にするか
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
        $register(str_repeat('a', 60).'12345') // 65文字（英字＋数字）
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('数字を含まないと拒否される', function () use ($register) {
        $register('abcdefgh')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('英字を含まないと拒否される', function () use ($register) {
        $register('12345678')
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    });

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

    it('ポリシーを満たすパスワードは受け付けられる', function () use ($register) {
        $register('valid-password1')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    });
});

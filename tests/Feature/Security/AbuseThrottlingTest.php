<?php

declare(strict_types=1);

use App\Models\User;

describe('濫用対策のレート制限', function () {
    // 大量登録・メール送信・外部 API 呼び出し・パスワード総当たりで、ワーカーと送信枠を使い切らせない。
    it('認証まわりのエンドポイントは上限を超えると 429 を返す', function (string $method, Closure $url, int $maxAttempts, bool $authenticated) {
        if ($authenticated) {
            $this->actingAs(User::factory()->create())
                ->withSession(['auth.password_confirmed_at' => time()]);
        }

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            expect($this->call($method, $url())->getStatusCode())->not->toBe(429);
        }

        $this->call($method, $url())->assertTooManyRequests();
    })->with([
        '会員登録' => ['POST', fn (): string => route('register.store'), 10, false],
        'パスワード再設定メールの送信' => ['POST', fn (): string => route('password.email'), 10, false],
        'パスワード再設定' => ['POST', fn (): string => route('password.update'), 10, false],
        'パスワード確認' => ['POST', fn (): string => route('password.confirm.store'), 5, true],
        'パスワード変更' => ['PUT', fn (): string => route('user-password.update'), 6, true],
        'メールアドレス変更の申請' => ['PATCH', fn (): string => route('profile.update'), 6, true],
        'メールアドレス変更の確定' => ['POST', fn (): string => route('email-change.complete', ['userId' => '1']), 10, false],
        'メールアドレス変更の取り消し' => ['POST', fn (): string => route('email-change.revert.complete', ['userId' => '1']), 10, false],
        'Google ログインのコールバック' => ['GET', fn (): string => route('auth.google.callback'), 10, false],
        'Google 連携の確定' => ['POST', fn (): string => route('auth.google.link.complete', ['userId' => '1']), 10, false],
    ]);

    it('IP ごとの上限は接続元ごとに数える', function () {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->post(route('register.store'));
        }

        expect($this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post(route('register.store'))->getStatusCode())
            ->not->toBe(429);
    });

    // 資格情報 ID は送る側が決められ、セッションも Cookie を捨てれば作り直せるので、接続元でも数える。
    // 資格情報 ID を接続元の集計キーと同じ形（ip）にしても、別のカウンタへ逃がさない。
    it('パスキーの上限は資格情報 ID を変えても接続元ごとに数える', function () {
        $loginWithPasskey = fn (string $ip, string $credentialId) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('passkey.login'), ['credential' => ['id' => $credentialId]]);

        for ($attempt = 0; $attempt < 300; $attempt++) {
            expect($loginWithPasskey('192.0.2.1', "credential-{$attempt}")->getStatusCode())->not->toBe(429);
        }

        $loginWithPasskey('192.0.2.1', 'credential-next')->assertTooManyRequests();
        $loginWithPasskey('192.0.2.1', 'ip')->assertTooManyRequests();
        expect($loginWithPasskey('192.0.2.2', 'credential-next')->getStatusCode())->not->toBe(429);
    });

    it('パスワード確認の上限は利用者ごとに数える', function () {
        $this->actingAs(User::factory()->create());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.confirm.store'));
        }

        $this->actingAs(User::factory()->create());

        expect($this->post(route('password.confirm.store'))->getStatusCode())->not->toBe(429);
    });
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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

    it('プロフィール更新とパスワード変更の上限は別々に数える', function () {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->patch(route('profile.update'));
        }

        expect($this->put(route('user-password.update'))->getStatusCode())->not->toBe(429);
    });

    describe('確認メールの 1 時間あたりの上限', function () {
        beforeEach(fn () => Mail::fake());

        $changeEmail = function (User $user, string $email, string $ip = '192.0.2.1') {
            // 1 分あたりの上限に当たらないよう、1 回ごとに時間を進める。
            test()->travel(61)->seconds();

            return test()->actingAs($user)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->withServerVariables(['REMOTE_ADDR' => $ip])
                ->from(route('profile.edit'))
                ->patch(route('profile.update'), ['name' => $user->name, 'email' => $email]);
        };

        it('メールアドレスの変更は、確認メールを送るものだけを利用者ごとに数える', function () use ($changeEmail) {
            $user = User::factory()->create();

            for ($attempt = 0; $attempt < 10; $attempt++) {
                $changeEmail($user, $user->email)->assertSessionHasNoErrors();
            }

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $changeEmail($user, "new{$attempt}@example.com")->assertSessionHas('status', 'email-change-link-sent');
            }

            $changeEmail($user, 'new5@example.com')->assertSessionHasErrors([
                'email' => '確認メールの送信が続いたため、しばらく時間をおいてから再度お試しください。',
            ]);
        });

        it('メールアドレスの変更は、接続元ごとにも数える', function () use ($changeEmail) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $changeEmail(User::factory()->create(), "new{$attempt}@example.com")->assertSessionHasNoErrors();
            }

            $changeEmail(User::factory()->create(), 'new10@example.com')->assertSessionHasErrors('email');
            $changeEmail(User::factory()->create(), 'new11@example.com', '192.0.2.2')->assertSessionHasNoErrors();
        });

        it('確認メールの再送は、利用者ごとに数える', function () {
            $user = User::factory()->unverified()->create();

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->travel(61)->seconds();
                expect($this->actingAs($user)->post(route('verification.send'))->getStatusCode())->not->toBe(429);
            }

            $this->travel(61)->seconds();
            $this->actingAs($user)->post(route('verification.send'))->assertTooManyRequests();
        });

        it('確認メールの再送が上限に達しても、届いたリンクで確認できる', function () {
            $user = User::factory()->unverified()->create();

            for ($attempt = 0; $attempt < 6; $attempt++) {
                $this->travel(61)->seconds();
                $this->actingAs($user)->post(route('verification.send'));
            }

            $this->actingAs($user)->get(URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->getKey(), 'hash' => sha1($user->email)],
            ))->assertRedirect();

            expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
        });
    });
});

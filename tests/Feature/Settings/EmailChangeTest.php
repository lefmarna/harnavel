<?php

declare(strict_types=1);

use App\Mail\EmailChangedMail;
use App\Mail\VerifyNewEmailMail;
use App\Models\User;
use App\ViewModels\Auth\EmailLinkPageViewModel;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

mutates(EmailLinkPageViewModel::class);

function requestEmailChange(User $user, string $email): string
{
    Mail::fake();

    test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $email])
        ->assertSessionHasNoErrors();

    $url = null;
    Mail::assertSent(VerifyNewEmailMail::class, function (VerifyNewEmailMail $mail) use ($email, &$url): bool {
        $url = $mail->url;

        return $mail->hasTo($email);
    });

    forgetRequestState();

    return (string) $url;
}

function completeEmailChange(string $verifyUrl, string $previousEmail): void
{
    Mail::fake();

    test()->post($verifyUrl)->assertRedirect(route('email-change.completed'));

    Mail::assertSent(EmailChangedMail::class, fn (EmailChangedMail $mail): bool => $mail->hasTo($previousEmail));

    forgetRequestState();
}

describe('メールアドレスの変更', function () {
    it('新しいアドレスを確認するまでメールアドレスを変えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);

        requestEmailChange($user, 'new@example.com');

        expect($user->refresh()->email)->toBe('old@example.com')
            ->and($user->email_verified_at)->not->toBeNull();
    });

    it('確認メールを送ったことを画面に伝える', function () {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertRedirect(settingsUrl('profile'))
            ->assertSessionHas('status', 'email-change-link-sent');
    });

    it('大文字小文字だけの変更では確認メールを送らない', function () {
        config(['fortify.lowercase_usernames' => false]);
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'User@Example.com'])
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    });

    it('確認メールを送れなかったときはエラーを表示し、例外を報告する', function () {
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection refused'));
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->from(settingsUrl('profile'))
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasErrors(['email' => '確認メールを送信できませんでした。時間をおいて再度お試しください。']);

        Exceptions::assertReported(TransportException::class);
        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('切り替えた直後でも次の変更を申請できる', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');

        requestEmailChange($user->refresh(), 'next@example.com');
    });

    it('保存済みのアドレスと大文字小文字だけが違う入力は、確認メールを送らずに保存する', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'User@Example.com']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'User@Example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('status');

        Mail::assertNothingSent();
        expect($user->refresh()->email)->toBe('user@example.com');
    });

    it('確認リンクを開くと確認ページを表示し、まだ変更しない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/verify')
                ->where('status', 'confirm')
                ->where('email', 'new@example.com')
                ->where('actionUrl', $url)
                ->where('seo.title', pageCopy('pages.auth.email_change.verify.confirm.title'))
                ->where('seo.description', pageCopy('pages.auth.email_change.verify.confirm.description')));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('確認リンクで確定すると新しいアドレスに切り替えて確認済みにする', function () {
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        Mail::fake();

        $this->post($url)->assertRedirect(route('email-change.completed'));

        expect($user->refresh()->email)->toBe('new@example.com')
            ->and($user->email_verified_at)->not->toBeNull();
    });

    it('変更の完了画面は再読み込みしても完了を表示する', function () {
        $this->get(route('email-change.completed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/verify')
                ->where('status', 'success')
                ->where('seo.title', pageCopy('pages.auth.email_change.verify.success.title'))
                ->where('seo.description', pageCopy('pages.auth.email_change.verify.success.description')));
    });

    it('切り替えたら旧アドレスへ、操作用のリンクを含まず、取り戻す手順を案内する通知を送る', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        Mail::fake();

        $this->post($url)->assertRedirect(route('email-change.completed'));

        Mail::assertSent(EmailChangedMail::class, function (EmailChangedMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('old@example.com')
                && ! str_contains($html, '/settings/email/')
                && str_contains($html, 'パスワードを変更')
                && str_contains($html, '覚えのないパスキーやGoogleログイン連携を削除・解除');
        });
    });

    it('メールアドレスの変更を申請・確定の順に記録する', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        Log::spy();

        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');

        Log::shouldHaveReceived('info')->with('User email change was requested.', ['user_id' => $user->getKey()])->once();
        Log::shouldHaveReceived('info')->with('User email was changed.', ['user_id' => $user->getKey()])->once();
    });

    it('旧アドレスへの通知を送れなくても切り替えは完了し、例外を報告する', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection refused'));

        $this->post($url)->assertRedirect(route('email-change.completed'));

        Exceptions::assertReported(TransportException::class);
        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('同じ確認リンクをもう一度使っても成功を表示し、通知を重ねて送らない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        completeEmailChange($url, 'old@example.com');
        Mail::fake();

        $this->post($url)->assertRedirect(route('email-change.completed'));

        Mail::assertNothingSent();
    });

    it('申請後にメールアドレスが変わっていたら確認リンクを使えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $first = requestEmailChange($user, 'first@example.com');
        $second = requestEmailChange($user, 'second@example.com');
        completeEmailChange($second, 'old@example.com');

        $this->post($first)->assertRedirect(route('email-change.failed'));

        expect($user->refresh()->email)->toBe('second@example.com');
    });

    it('変更の失敗画面は再読み込みしても失敗を表示する', function () {
        $this->get(route('email-change.failed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/verify')
                ->where('status', 'error')
                ->where('seo.title', pageCopy('pages.auth.email_change.verify.error.title'))
                ->where('seo.description', pageCopy('pages.auth.email_change.verify.error.description')));
    });

    it('セッションを失効させた後は、それより前の確認リンクを使えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'attacker@example.com');
        $user->refresh()->revokeSessions();
        $user->save();

        $this->post($url)->assertRedirect(route('email-change.failed'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('同じ秒のうちに元のアドレスへ戻しても、それより前の確認リンクで再び変更できない', function () {
        $this->freezeSecond();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'attacker@example.com');
        completeEmailChange($url, 'old@example.com');
        completeEmailChange(requestEmailChange($user->refresh(), 'old@example.com'), 'attacker@example.com');

        $this->post($url)->assertRedirect(route('email-change.failed'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('申請の後に大文字小文字だけを変えていても確認リンクを使える', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        $user->refresh()->forceFill(['email' => 'Old@Example.com'])->save();

        $this->post($url)->assertRedirect(route('email-change.completed'));

        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('確定の後に大文字小文字だけを変えていても、同じ確認リンクは成功を表示する', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        completeEmailChange($url, 'old@example.com');
        $user->refresh()->forceFill(['email' => 'New@Example.com'])->save();
        Mail::fake();

        $this->post($url)->assertRedirect(route('email-change.completed'));

        Mail::assertNothingSent();
        expect($user->refresh()->email)->toBe('New@Example.com');
    });

    it('確認までに新しいアドレスを別のアカウントが使い始めていたら変更しない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        User::factory()->create(['email' => 'new@example.com']);

        $this->post($url)->assertRedirect(route('email-change.failed'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('期限切れや改ざんされた確認リンクは使えない', function (Closure $tamper) {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $tamper(requestEmailChange($user, 'new@example.com'));

        $this->post($url)->assertRedirect(route('email-change.failed'));

        expect($user->refresh()->email)->toBe('old@example.com');
    })->with([
        '期限切れ' => [function (string $url): string {
            test()->travel(61)->minutes();

            return $url;
        }],
        '署名の改ざん' => [fn (string $url): string => $url.'0'],
        '別のユーザー' => [fn (string $url): string => preg_replace('#/verify/\d+#', '/verify/999999', $url) ?? $url],
    ]);
});

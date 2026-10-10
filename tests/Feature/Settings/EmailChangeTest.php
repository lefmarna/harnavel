<?php

declare(strict_types=1);

use App\Mail\EmailChangedMail;
use App\Mail\VerifyNewEmailMail;
use App\Models\User;
use App\Services\Account\EmailChangeService;
use App\ViewModels\Auth\EmailChangePageViewModel;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

mutates(EmailChangePageViewModel::class);

/**
 * プロフィールから新しいメールアドレスを申請し、そのアドレスへ届いた確認リンクを返す。
 */
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

/**
 * 新しいアドレスへの切り替えを確定し、旧アドレスへ届いた取り消しリンクを返す。
 */
function completeEmailChange(string $verifyUrl, string $previousEmail): string
{
    Mail::fake();

    test()->post($verifyUrl)->assertOk();

    $url = null;
    Mail::assertSent(EmailChangedMail::class, function (EmailChangedMail $mail) use ($previousEmail, &$url): bool {
        $url = $mail->url;

        return $mail->hasTo($previousEmail);
    });

    forgetRequestState();

    return (string) $url;
}

describe('メールアドレスの変更', function () {
    // セッションを奪った第三者が自分のアドレスへ書き換え、再設定メールで乗っ取るのを防ぐ（TADR-0016）。
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
            ->assertRedirect(route('profile.edit'))
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
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasErrors(['email' => '確認メールを送信できませんでした。時間をおいて再度お試しください。']);

        Exceptions::assertReported(TransportException::class);
        expect($user->refresh()->email)->toBe('old@example.com');
    });

    // 使える取り消しリンクを1つに保つ。第三者が自分のアドレスを経由させて、持ち主のリンクと競う取り消しリンクを受け取れないようにする。
    it('切り替えから7日間は次の変更を受け付けない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $this->travel(7 * 24 * 60 - 1)->minutes();
        Mail::fake();

        $this->actingAs($user->refresh())
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'next@example.com'])
            ->assertSessionHasErrors(['email' => 'メールアドレスは、前回の変更から7日間は変更できません。']);

        Mail::assertNothingSent();
        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('切り替えから7日を過ぎれば次の変更を申請できる', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $this->travel(7 * 24 * 60 + 1)->minutes();

        requestEmailChange($user->refresh(), 'next@example.com');
    });

    // 次の変更を受け付けるのは、取り消しリンクが使えなくなった後だけにする。
    it('取り消しリンクが使えるうちは次の変更を受け付けず、使えなくなった直後から受け付ける', function (int $seconds, bool $revertUsable) {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->travelTo(now()->startOfSecond()->addMilliseconds(900));
        $requestUrl = requestEmailChange($user, 'new@example.com');
        $this->travel(1)->seconds();
        $revertUrl = completeEmailChange($requestUrl, 'old@example.com');
        $this->travelTo($user->refresh()->email_changed_at->addDays(7)->addSeconds($seconds)->addMilliseconds(500));
        Mail::fake();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'next@example.com']);

        if ($revertUsable) {
            $response->assertSessionHasErrors('email');
            Mail::assertNothingSent();
        } else {
            $response->assertSessionHasNoErrors();
            Mail::assertSent(VerifyNewEmailMail::class);
        }

        forgetRequestState();
        $this->get($revertUrl)
            ->assertInertia(fn (Assert $page) => $page->where('status', $revertUsable ? 'confirm' : 'error'));
    })->with([
        '期限の秒' => [0, true],
        '期限の次の秒' => [1, false],
    ]);

    // 申請の時点では制限にかからなくても、確定の時点で7日以内なら切り替えない。判定と切り替えの間の確定を防ぐ。
    it('確定する時点で前回の切り替えから7日以内なら切り替えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $stale = $user->refresh()->replicate()->forceFill(['email_changed_at' => null]);
        $stale->id = $user->id;
        Mail::fake();
        app(EmailChangeService::class)->request($stale, 'next@example.com');
        $url = Mail::sent(VerifyNewEmailMail::class)->first()->url;

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'error'));

        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('名前だけの変更は、切り替えから7日以内でも受け付ける', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');

        $this->actingAs($user->refresh())
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), ['name' => 'Renamed', 'email' => 'new@example.com'])
            ->assertSessionHasNoErrors();

        expect($user->refresh()->name)->toBe('Renamed');
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
                ->where('seo.title', 'メールアドレス変更の確認')
                ->where('seo.description', '新しいメールアドレスへの変更を確認してください。'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('確認リンクで確定すると新しいアドレスに切り替えて確認済みにする', function () {
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        Mail::fake();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/verify')
                ->where('status', 'success')
                ->where('seo.title', 'メールアドレス変更完了')
                ->where('seo.description', 'メールアドレスの変更が完了しました。'));

        expect($user->refresh()->email)->toBe('new@example.com')
            ->and($user->email_verified_at)->not->toBeNull();
    });

    it('切り替えたら旧アドレスへ取り消しリンク付きの通知を送る', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');

        expect($revertUrl)->toContain('/settings/email/revert/');
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

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        Exceptions::assertReported(TransportException::class);
        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('同じ確認リンクをもう一度使っても成功を表示し、通知を重ねて送らない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        completeEmailChange($url, 'old@example.com');
        Mail::fake();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        Mail::assertNothingSent();
    });

    // 申請後に別の変更が先に確定したなら、古い申請の前提が崩れている。
    it('申請後にメールアドレスが変わっていたら確認リンクを使えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $first = requestEmailChange($user, 'first@example.com');
        $second = requestEmailChange($user, 'second@example.com');
        completeEmailChange($second, 'old@example.com');

        $this->post($first)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'error')
                ->where('seo.title', 'メールアドレス変更エラー')
                ->where('seo.description', 'メールアドレスを変更できませんでした。'));

        expect($user->refresh()->email)->toBe('second@example.com');
    });

    // セッションを奪った第三者が申請した変更を、持ち主がセッションを失効させた後に確定させない。
    it('セッションを失効させた後は、それより前の確認リンクを使えない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'attacker@example.com');
        $user->refresh()->revokeSessions();
        $user->save();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'error'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('取り消した後は、それより前の確認リンクで再び変更できない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'attacker@example.com');
        $revertUrl = completeEmailChange($url, 'old@example.com');
        $this->post($revertUrl)->assertOk();
        forgetRequestState();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'error'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('申請の後に大文字小文字だけを変えていても確認リンクを使える', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        $user->refresh()->forceFill(['email' => 'Old@Example.com'])->save();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('確定の後に大文字小文字だけを変えていても、同じ確認リンクは成功を表示する', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        completeEmailChange($url, 'old@example.com');
        $user->refresh()->forceFill(['email' => 'New@Example.com'])->save();
        Mail::fake();

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        Mail::assertNothingSent();
        expect($user->refresh()->email)->toBe('New@Example.com');
    });

    it('確認までに新しいアドレスを別のアカウントが使い始めていたら変更しない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = requestEmailChange($user, 'new@example.com');
        User::factory()->create(['email' => 'new@example.com']);

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'error'));

        expect($user->refresh()->email)->toBe('old@example.com');
    });

    it('期限切れや改ざんされた確認リンクは使えない', function (Closure $tamper) {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $tamper(requestEmailChange($user, 'new@example.com'));

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'error'));

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

describe('メールアドレス変更の取り消し', function () {
    it('取り消しリンクを開くと確認ページを表示し、まだ取り消さない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');

        $this->get($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/revert')
                ->where('status', 'confirm')
                ->where('email', 'old@example.com')
                ->where('actionUrl', $revertUrl)
                ->where('seo.title', 'メールアドレス変更の取り消し')
                ->where('seo.description', 'メールアドレスの変更を取り消すか確認してください。'));

        expect($user->refresh()->email)->toBe('new@example.com');
    });

    // 変更した第三者が用意したパスワード・パスキー・Google 連携・セッションを、持ち主が取り戻した後に残さない（TADR-0016）。
    it('取り消すと旧アドレスに戻し、資格情報とセッションを無効にする', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'old@example.com', 'google_id' => 'google-attacker']);
        $user->passkeys()->create([
            'name' => 'Attacker key',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $sessionId = loginFromNewDevice($user->refresh());

        forgetRequestState();
        $this->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/email-change/revert')
                ->where('status', 'success')
                ->where('seo.title', 'メールアドレス変更の取り消し完了')
                ->where('seo.description', 'メールアドレスの変更を取り消しました。'));

        $user->refresh();
        expect($user->email)->toBe('old@example.com')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->password)->toBeNull()
            ->and($user->google_id)->toBeNull()
            ->and($user->passkeys()->count())->toBe(0);
        visitDashboardFrom($sessionId)->assertRedirect(route('login'));
    });

    // 第三者がパスワードを再設定した後でも、持ち主の端末に残る失効済みのログインで復旧を妨げない。
    it('失効したログインが残る端末からでも取り消しリンクを使える', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $sessionId = loginFromNewDevice($user->refresh());
        $user->refresh()->revokeSessions();
        $user->save();

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->get($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'confirm'));

        forgetRequestState();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        expect($user->refresh()->email)->toBe('old@example.com');
        $this->assertGuest();
    });

    // 盗まれたセッションで始めた更新が、取り消しの後に第三者のアドレスを書き戻さないようにする。
    it('プロフィール更新の途中で取り消されたら、その更新を拒否する', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        completeEmailChange(requestEmailChange($user, 'attacker@example.com'), 'owner@example.com');
        Event::listen(RouteMatched::class, function () use ($user): void {
            app(EmailChangeService::class)->revert((string) $user->getKey(), 'owner@example.com');
        });

        $this->actingAs($user->refresh())
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'Attacker@Example.com'])
            ->assertConflict();

        expect($user->refresh()->email)->toBe('owner@example.com');
    });

    it('取り消しを記録する', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        Log::spy();

        $this->post($revertUrl)->assertOk();

        Log::shouldHaveReceived('info')->with('User email change was reverted.', ['user_id' => $user->getKey()])->once();
    });

    // 取り消した後に持ち主が設定し直したパスワードを、同じリンクで再び消さない。
    it('すでに旧アドレスに戻っていれば何も変えずに完了を表示する', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $this->post($revertUrl)->assertOk();
        forgetRequestState();
        $user->refresh()->forceFill(['password' => 'reset-password1'])->save();
        $password = $user->password;

        $this->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        expect($user->refresh()->password)->toBe($password);
    });

    it('旧アドレスに戻った後に大文字小文字だけを変えていても、同じリンクで資格情報を消さない', function () {
        config(['fortify.lowercase_usernames' => false]);
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $this->post($revertUrl)->assertOk();
        forgetRequestState();
        $user->refresh()->forceFill(['email' => 'Old@Example.com', 'password' => 'reset-password1'])->save();
        $password = $user->password;

        $this->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'success'));

        expect($user->refresh()->password)->toBe($password);
    });

    it('旧アドレスを別のアカウントが使い始めていたら取り消せない', function () {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        User::factory()->create(['email' => 'old@example.com']);

        $this->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'error')
                ->where('seo.title', 'メールアドレス変更の取り消しエラー')
                ->where('seo.description', 'メールアドレスの変更を取り消せませんでした。'));

        expect($user->refresh()->email)->toBe('new@example.com');
    });

    it('取り消しリンクは7日間使える', function (int $minutes, string $status) {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $revertUrl = completeEmailChange(requestEmailChange($user, 'new@example.com'), 'old@example.com');
        $this->travel($minutes)->minutes();

        $this->post($revertUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', $status));
    })->with([
        '7日の直前' => [7 * 24 * 60 - 1, 'success'],
        '7日を過ぎた後' => [7 * 24 * 60 + 1, 'error'],
    ]);
});

<?php

declare(strict_types=1);

use App\Mail\AccountSecurityMail;
use App\Models\User;
use App\Services\GoogleAuth\GoogleAuthService;
use App\Services\Observability\ProductionExceptionNotifier;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Symfony\Component\Mailer\Exception\TransportException;

function changePasswordFromSettings(User $user): void
{
    test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->from(settingsUrl('security'))
        ->put(route('user-password.update'), [
            'current_password' => UserFactory::DEFAULT_PASSWORD,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])
        ->assertSessionHasNoErrors();
}

describe('セキュリティに関わる変更の通知', function () {
    it('パスワードを変更したら本人に知らせる', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        changePasswordFromSettings($user);

        Mail::assertSent(AccountSecurityMail::class, fn (AccountSecurityMail $mail): bool => $mail->hasTo('owner@example.com')
            && $mail->event === 'password_changed');
    });

    it('パスキーを追加したら本人に知らせる', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $passkey = $user->passkeys()->create([
            'name' => 'New key',
            'credential_id' => 'credential-1',
            'credential' => ['id' => 'credential-1'],
        ]);

        event(new PasskeyRegistered($user, $passkey));

        Mail::assertSent(AccountSecurityMail::class, fn (AccountSecurityMail $mail): bool => $mail->hasTo('owner@example.com')
            && $mail->event === 'passkey_added');
    });

    it('Google ログインを連携したら本人に知らせる', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com', 'google_id' => null]);

        expect(app(GoogleAuthService::class)->confirmLink((string) $user->getKey(), 'google-1', 'owner@example.com', '0'))->toBeTrue();

        Mail::assertSent(AccountSecurityMail::class, fn (AccountSecurityMail $mail): bool => $mail->hasTo('owner@example.com')
            && $mail->event === 'google_linked');
    });

    it('すでに同じ Google アカウントと連携していれば知らせない', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com', 'google_id' => 'google-1']);

        expect(app(GoogleAuthService::class)->confirmLink((string) $user->getKey(), 'google-1', 'owner@example.com', '0'))->toBeTrue();

        Mail::assertNotSent(AccountSecurityMail::class);
    });

    it('連携できなかったときは知らせない', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com', 'google_id' => 'google-other']);

        expect(app(GoogleAuthService::class)->confirmLink((string) $user->getKey(), 'google-1', 'owner@example.com', '0'))->toBeFalse();

        Mail::assertNotSent(AccountSecurityMail::class);
    });

    it('通知を送れなくても変更は完了し、個人情報を含めずに失敗を記録して Discord へ知らせる', function () {
        Exceptions::fake();
        Log::spy();
        $exception = new TransportException('550 <owner@example.com> User unknown');
        Mail::shouldReceive('to')->andThrow($exception);
        $this->mock(ProductionExceptionNotifier::class)
            ->shouldReceive('report')->once()->with($exception, Mockery::type(Request::class));
        $user = User::factory()->create();

        changePasswordFromSettings($user);

        Exceptions::assertNothingReported();
        Log::shouldHaveReceived('warning')->with('Account security notification could not be sent.', [
            'exception_class' => TransportException::class,
        ])->once();
        expect(Hash::check('new-password1', $user->refresh()->password))->toBeTrue();
    });

    // 本人確認の手段を失っていてセキュリティ設定に進めない人には、ログアウトしてから再設定するよう案内する。
    it('通知には変更の内容と、ログインの有無に応じた対応の案内を載せる', function (string $event, string $subject, string $body) {
        config(['app.display_name' => 'テストアプリ']);
        $mail = new AccountSecurityMail($event);

        $mail->assertHasSubject($subject);
        $mail->assertSeeInHtml($body);
        $mail->assertSeeInHtml('Googleログイン連携を削除・解除');
        $mail->assertSeeInOrderInHtml([
            'ログインしている場合',
            settingsUrl('security'),
            'ログインしていない場合や、セキュリティ設定で本人確認ができない場合',
            'ログアウトしてから',
            route('password.request'),
        ], escape: false);
    })->with([
        'パスワード' => ['password_changed', '【テストアプリ】パスワードが変更されました', 'パスワードが変更されました。'],
        'パスキー' => ['passkey_added', '【テストアプリ】パスキーが追加されました', 'パスキーが追加されました。'],
        'Google' => ['google_linked', '【テストアプリ】Googleログインが連携されました', 'Googleログインが連携されました。'],
    ]);
});

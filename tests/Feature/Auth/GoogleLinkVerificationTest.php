<?php

declare(strict_types=1);

use App\Services\GoogleAuth\GoogleAuthServiceContract;
use App\ViewModels\Auth\EmailLinkPageViewModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EmailLinkPageViewModel::class);

describe('Google 連携リンク検証', function () {
    it('署名付き Google 連携リンクの GET は確認ページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
            'session_generation' => '0',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'confirm')
                ->where('email', 'existing@example.com')
                ->where('actionUrl', $url)
                ->where('seo.title', pageCopy('pages.auth.google_link.confirm.title'))
                ->where('seo.description', pageCopy('pages.auth.google_link.confirm.description')));
    });

    it('署名付き Google 連携リンクの POST で連携を確定し、完了画面へ移る', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldReceive('confirmLink')
            ->once()
            ->with('1', 'google-123', 'existing@example.com', '0')
            ->andReturnTrue();
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
            'session_generation' => '0',
        ]);

        $this->post($url)->assertRedirect(route('auth.google.link.completed'));
    });

    it('Google 連携の完了画面は再読み込みしても完了を表示する', function () {
        $this->get(route('auth.google.link.completed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'success')
                ->where('seo.title', pageCopy('pages.auth.google_link.success.title'))
                ->where('seo.description', pageCopy('pages.auth.google_link.success.description')));
    });

    it('Google 連携リンクの署名が改ざんされている場合は失敗画面へ移る', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
            'session_generation' => '0',
        ]);

        $invalidUrl = preg_replace('/signature=[^&]+/', 'signature=invalid', $url);
        expect($invalidUrl)->toBeString();

        $this->post($invalidUrl)->assertRedirect(route('auth.google.link.failed'));
    });

    it('Google 連携リンクの payload に必須項目が欠けている場合はエラーページを表示する', function (array $payload) {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', $payload);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));
    })->with([
        'google_id が無い' => [['email' => 'existing@example.com', 'session_generation' => '0']],
        'email が無い' => [['google_id' => 'google-123', 'session_generation' => '0']],
        'session_generation が無い' => [['google_id' => 'google-123', 'email' => 'existing@example.com']],
    ]);

    it('Google 連携確定が失敗したときは失敗画面へ移る', function () {
        Log::spy();

        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldReceive('confirmLink')
            ->once()
            ->with('1', 'google-123', 'existing@example.com', '0')
            ->andReturnFalse();
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
            'session_generation' => '0',
        ]);

        $this->post($url)->assertRedirect(route('auth.google.link.failed'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google link verification could not be confirmed.'
                    && $context === ['user_id' => '1'];
            })
            ->once();
    });

    it('Google 連携確定が例外で失敗してもログにバインディング値が残らない', function () {
        Log::spy();

        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldReceive('confirmLink')
            ->once()
            ->andThrow(new QueryException(
                'mysql',
                'update `users` set `google_id` = ? where `email` = ?',
                ['google-123', 'existing@example.com'],
                new Exception('SQLSTATE[23000]: Integrity constraint violation'),
            ));
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
            'session_generation' => '0',
        ]);

        $this->post($url)->assertRedirect(route('auth.google.link.failed'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google link verification failed.'
                    && $context === ['exception_class' => QueryException::class];
            })
            ->once();
    });

    it('Google 連携の失敗画面は再読み込みしても失敗を表示する', function () {
        $this->get(route('auth.google.link.failed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error')
                ->where('seo.title', pageCopy('pages.auth.google_link.error.title'))
                ->where('seo.description', pageCopy('pages.auth.google_link.error.description')));
    });
});

/**
 * @param  array<string, string>  $payload
 */
function googleLinkVerifyUrl(string $userId, array $payload, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute('auth.google.link.verify', $expiresAt ?? now()->addHour(), [
        'userId' => $userId,
        'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
    ]);
}

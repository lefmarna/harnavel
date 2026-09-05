<?php

declare(strict_types=1);

use App\Services\GoogleAuth\GoogleAuthServiceContract;
use App\ViewModels\Auth\GoogleLinkVerifyPageViewModel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

mutates(GoogleLinkVerifyPageViewModel::class);

describe('Google 連携リンク検証', function () {
    it('署名付き Google 連携リンクの GET は確認ページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'confirm')
                ->where('email', 'existing@example.com')
                ->where('actionUrl', $url)
                ->has('seo.title'));
    });

    it('署名付き Google 連携リンクは署名対象外のクエリが付いても確認ページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ]).'&utm_source=test';

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'confirm'));
    });

    it('署名付き Google 連携リンクの POST で成功ページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldReceive('confirmLink')
            ->once()
            ->with('1', 'google-123', 'existing@example.com')
            ->andReturnTrue();
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ]);

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'success')
                ->has('seo.title'));
    });

    it('期限切れの Google 連携リンクはエラーページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ], now()->subMinute());

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));
    });

    it('Google 連携リンクの署名が改ざんされている場合はエラーページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ]);

        $invalidUrl = preg_replace('/signature=[^&]+/', 'signature=invalid', $url);
        expect($invalidUrl)->toBeString();

        $this->post($invalidUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));
    });

    it('Google 連携リンクの payload が不正な場合はエラーページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = URL::temporarySignedRoute('auth.google.link.verify', now()->addHour(), [
            'userId' => '1',
            'payload' => 'not-encrypted',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));
    });

    it('Google 連携リンクに payload が無い場合はエラーページを表示する', function () {
        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = URL::temporarySignedRoute('auth.google.link.verify', now()->addHour(), [
            'userId' => '1',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));
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
        'google_id が無い' => [['email' => 'existing@example.com']],
        'google_id が空文字' => [['google_id' => '', 'email' => 'existing@example.com']],
        'email が無い' => [['google_id' => 'google-123']],
        'email が空文字' => [['google_id' => 'google-123', 'email' => '']],
    ]);

    it('Google 連携確定が失敗したときはエラーページを表示する', function () {
        Log::spy();

        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldReceive('confirmLink')
            ->once()
            ->with('1', 'google-123', 'existing@example.com')
            ->andReturnFalse();
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = googleLinkVerifyUrl('1', [
            'google_id' => 'google-123',
            'email' => 'existing@example.com',
        ]);

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));

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
        ]);

        $this->post($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/google-link/verify')
                ->where('status', 'error'));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google link verification failed.'
                    && $context === ['exception_class' => QueryException::class];
            })
            ->once();
    });

    it('Google 連携リンクの payload が復号できないときはログには例外クラス名だけを残す', function () {
        Log::spy();

        $service = Mockery::mock(GoogleAuthServiceContract::class);
        $service->shouldNotReceive('confirmLink');
        app()->instance(GoogleAuthServiceContract::class, $service);

        $url = URL::temporarySignedRoute('auth.google.link.verify', now()->addHour(), [
            'userId' => '1',
            'payload' => 'not-encrypted',
        ]);

        $this->get($url)->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Google link verification payload is invalid.'
                    && $context === ['exception_class' => DecryptException::class];
            })
            ->once();
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

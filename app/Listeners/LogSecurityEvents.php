<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * 入力されたメールアドレスなどの資格情報は記録しない（docs/logging-guidelines.md）。
 */
class LogSecurityEvents
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            CurrentDeviceLogout::class => 'logout',
            Failed::class => 'failed',
            Registered::class => 'registered',
            PasswordReset::class => 'passwordReset',
            PasskeyRegistered::class => 'passkeyRegistered',
            PasskeyDeleted::class => 'passkeyDeleted',
        ];
    }

    public function login(Login $event): void
    {
        Log::info('User logged in.', ['user_id' => $this->userId($event->user), 'remember' => $event->remember]);
    }

    public function logout(Logout|CurrentDeviceLogout $event): void
    {
        Log::info('User logged out.', ['user_id' => $this->userId($event->user)]);
    }

    public function failed(Failed $event): void
    {
        Log::info('Login failed.', ['user_id' => $this->userId($event->user)]);
    }

    public function registered(Registered $event): void
    {
        Log::info('User registered.', ['user_id' => $this->userId($event->user)]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        Log::info('User password was reset.', ['user_id' => $this->userId($event->user)]);
    }

    public function passkeyRegistered(PasskeyRegistered $event): void
    {
        Log::info('Passkey registered.', ['user_id' => $this->userId($event->user), 'passkey_id' => $event->passkey->getKey()]);
    }

    public function passkeyDeleted(PasskeyDeleted $event): void
    {
        Log::info('Passkey deleted.', ['user_id' => $this->userId($event->user), 'passkey_id' => $event->passkey->getKey()]);
    }

    private function userId(?Authenticatable $user): mixed
    {
        return $user?->getAuthIdentifier();
    }
}

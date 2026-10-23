<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\AccountSecurityMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 変更が確定した後に呼ぶ。送信の失敗は報告だけにする（TADR-0016）。
 */
class SecurityNotifier
{
    /**
     * @param  'password_changed'|'passkey_added'|'google_linked'  $event
     */
    public function notify(User $user, string $event): void
    {
        try {
            Mail::to($user->email)->send(new AccountSecurityMail($event));
        } catch (Throwable $e) {
            Log::warning('Account security notification could not be sent.', ['exception_class' => $e::class]);
        }
    }
}

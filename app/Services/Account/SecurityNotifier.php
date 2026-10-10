<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\AccountSecurityMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 資格情報が足されたり変えられたりしたことを本人に知らせ、盗まれたセッションでの操作に気づけるようにする（TADR-0016）。
 * 変更が確定した後に呼ぶ。変更はすでに済んでいるので、送れなくても失敗にせず報告だけする。
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
            report($e);
        }
    }
}

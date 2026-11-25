<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AccountSecurityMail extends Mailable
{
    /**
     * @param  'password_changed'|'passkey_added'|'google_linked'  $event
     */
    public function __construct(public readonly string $event) {}

    public function build(): self
    {
        $appName = config('app.display_name', config('app.name'));
        $appName = is_string($appName) ? $appName : 'Laravel';

        return $this
            ->subject(__("mail.account_security.{$this->event}.subject", ['app' => $appName]))
            ->view('emails.account_security')
            ->with([
                'appName' => $appName,
                'eventText' => __("mail.account_security.{$this->event}.event"),
                'time' => now()->format('Y年n月j日 H:i'),
                // 再設定の画面はログイン中には開けないので、ログインしたまま受け取った人にはセキュリティ設定を案内する。
                'securityUrl' => route('security.edit'),
                'resetUrl' => route('password.request'),
            ]);
    }
}

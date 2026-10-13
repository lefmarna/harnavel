<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function build(): self
    {
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');
        $appName = config('app.display_name', config('app.name'));
        $appName = is_string($appName) ? $appName : 'Laravel';

        return $this
            ->from(
                is_string($fromAddress) ? $fromAddress : '',
                is_string($fromName) ? $fromName : '',
            )
            ->subject(__('mail.email_changed.subject', ['app' => $appName]))
            ->view('emails.email_changed')
            ->with([
                'appName' => $appName,
                'profileUrl' => route('profile.edit'),
            ]);
    }
}

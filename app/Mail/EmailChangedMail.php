<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class EmailChangedMail extends Mailable
{
    public function build(): self
    {
        $appName = config('app.display_name', config('app.name'));
        $appName = is_string($appName) ? $appName : 'Laravel';

        return $this
            ->subject(__('mail.email_changed.subject', ['app' => $appName]))
            ->view('emails.email_changed')
            ->with([
                'appName' => $appName,
                'profileUrl' => route('profile.edit'),
            ]);
    }
}

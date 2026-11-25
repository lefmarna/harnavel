<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VerifyNewEmailMail extends Mailable
{
    public function __construct(public readonly string $url) {}

    public function build(): self
    {
        $appName = config('app.display_name', config('app.name'));
        $appName = is_string($appName) ? $appName : 'Laravel';

        return $this
            ->subject(__('mail.verify_new_email.subject', ['app' => $appName]))
            ->view('emails.verify_new_email')
            ->with([
                'appName' => $appName,
                'url' => $this->url,
            ]);
    }
}

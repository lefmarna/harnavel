<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class LinkGoogleAccountMail extends Mailable
{
    public function __construct(
        private readonly User $user,
        public readonly string $url,
    ) {}

    public function build(): self
    {
        $appName = config('app.display_name', config('app.name'));
        $appName = is_string($appName) ? $appName : 'Laravel';

        return $this
            ->subject(__('mail.link_google_account.subject', ['app' => $appName]))
            ->view('emails.link_google_account')
            ->with([
                'appName' => $appName,
                'user' => $this->user,
                'url' => $this->url,
            ]);
    }
}

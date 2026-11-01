<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LinkGoogleAccountMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly User $user,
        public readonly string $url,
    ) {}

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
            ->subject(__('mail.link_google_account.subject', ['app' => $appName]))
            ->view('emails.link_google_account')
            ->with([
                'appName' => $appName,
                'user' => $this->user,
                'url' => $this->url,
            ]);
    }
}

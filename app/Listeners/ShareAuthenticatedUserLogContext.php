<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Log;

class ShareAuthenticatedUserLogContext
{
    public function handle(Authenticated $event): void
    {
        Log::shareContext([
            'user_id' => $event->user->getAuthIdentifier(),
        ]);
    }
}

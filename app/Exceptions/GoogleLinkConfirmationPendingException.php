<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

class GoogleLinkConfirmationPendingException extends RuntimeException
{
    public function __construct(
        public readonly User $user,
        public readonly string $googleId,
    ) {
        parent::__construct('Google account link confirmation is pending.');
    }
}

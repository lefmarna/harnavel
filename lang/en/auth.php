<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'google' => [
        'unavailable' => 'Google sign-in is currently unavailable.',
        'failed' => 'Google sign-in failed. Please try again.',
        'email_unverified' => 'The email address on your Google account is not verified. Verify it with Google, then try again.',
        'email_missing' => 'We could not read the email address on your Google account. Please try another account.',
        'profile_failed' => 'We could not retrieve your Google account details. Please try again.',
        'email_taken' => 'This email address is already linked to a different Google account.',
        'reauthentication_unavailable' => 'Google identity confirmation is currently unavailable.',
        'reauthentication_mismatch' => 'Confirm your identity with the linked Google account.',
    ],
];

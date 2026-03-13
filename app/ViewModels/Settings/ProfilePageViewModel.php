<?php

declare(strict_types=1);

namespace App\ViewModels\Settings;

final readonly class ProfilePageViewModel
{
    public function __construct(
        private bool $mustVerifyEmail,
        private ?string $status,
    ) {}

    /**
     * @return array{
     *     mustVerifyEmail: bool,
     *     status: string|null,
     *     seo: array{title: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'mustVerifyEmail' => $this->mustVerifyEmail,
            'status' => $this->status,
            'seo' => [
                'title' => 'Profile settings',
            ],
        ];
    }
}

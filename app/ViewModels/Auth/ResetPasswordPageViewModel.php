<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class ResetPasswordPageViewModel extends PageViewModel
{
    public function __construct(
        private string $title,
        private string $description,
        private string $email,
        private string $token,
    ) {}

    /**
     * @return array{
     *     email: string,
     *     token: string,
     *     seo: array{title: string, description: string, robots: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'email' => $this->email,
            'token' => $this->token,
            'seo' => $this->noIndexSeo($this->title, $this->description),
        ];
    }
}

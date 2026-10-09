<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class ConfirmPasswordPageViewModel extends PageViewModel
{
    public function __construct(
        private string $title,
        private string $description,
        private bool $hasPasskeys,
        private bool $canConfirmWithGoogle,
        private ?string $error,
    ) {}

    /**
     * @return array{
     *     hasPasskeys: bool,
     *     canConfirmWithGoogle: bool,
     *     error: string|null,
     *     seo: array{title: string, description: string, robots: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'hasPasskeys' => $this->hasPasskeys,
            'canConfirmWithGoogle' => $this->canConfirmWithGoogle,
            'error' => $this->error,
            'seo' => $this->noIndexSeo($this->title, $this->description),
        ];
    }
}

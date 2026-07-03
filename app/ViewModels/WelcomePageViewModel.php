<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\RenderContext;

final readonly class WelcomePageViewModel extends PageViewModel
{
    public function __construct(
        private bool $canRegister,
    ) {}

    /**
     * @return array{
     *     canRegister: bool,
     *     seo: array{title: string, description: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        $description = config('app.description');

        return [
            'canRegister' => $this->canRegister,
            'seo' => $this->basicSeo('ホーム', is_string($description) ? $description : ''),
        ];
    }
}

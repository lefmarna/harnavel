<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\RenderContext;

final readonly class StaticPageViewModel extends PageViewModel
{
    public function __construct(
        private string $title,
        private string $description,
        private bool $noIndex = false,
    ) {}

    /**
     * @return array{seo: array{title: string, description: string, robots?: string}}
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'seo' => $this->noIndex
                ? $this->noIndexSeo($this->title, $this->description)
                : $this->basicSeo($this->title, $this->description),
        ];
    }
}

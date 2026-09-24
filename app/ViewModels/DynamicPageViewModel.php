<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\RenderContext;

/**
 * 専用の ViewModel を作らないページで、ページ固有の props に SEO を合成する。
 */
final readonly class DynamicPageViewModel extends PageViewModel
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        private string $title,
        private string $description,
        private array $props = [],
        private bool $noIndex = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            ...$this->props,
            'seo' => $this->noIndex
                ? $this->noIndexSeo($this->title, $this->description)
                : $this->basicSeo($this->title, $this->description),
        ];
    }
}

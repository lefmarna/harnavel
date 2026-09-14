<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\RenderContext;

/**
 * ページ固有の props の型は fragment ViewModel が定義し、ここでは SEO と合成する。
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

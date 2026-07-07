<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\RenderContext;

/**
 * 任意の props を伴うドメインページ用の汎用 ViewModel。
 *
 * props を持たず SEO だけを返すページは StaticPageViewModel を使う。
 * ドメインページの props 生成は各 fragment ViewModel が担い（array-shape
 * PHPDoc もそちらが持つ）、本クラスはそれに basicSeo / noIndexSeo を合成して
 * Inertia へ渡す。固有 props の形をページ単位で型として固定したい場合は、
 * 従来どおりページ 1:1 の具象 PageViewModel を作る（TADR-0005）。
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

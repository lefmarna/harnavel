<?php

declare(strict_types=1);

namespace App\ViewModels;

final readonly class PageViewModel
{
    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $seo
     */
    public function __construct(
        private string $title,
        private string $description,
        private array $props = [],
        private array $seo = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->props,
            'seo' => [
                'title' => $this->title,
                'description' => $this->description,
                ...$this->seo,
            ],
        ];
    }
}

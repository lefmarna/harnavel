<?php

declare(strict_types=1);

namespace App\ViewModels;

use Inertia\ProvidesInertiaProperties;

abstract readonly class PageViewModel implements ProvidesInertiaProperties
{
    /**
     * @return array{title: string, description: string}
     */
    protected function basicSeo(string $title, string $description): array
    {
        return [
            'title' => $title,
            'description' => $description,
        ];
    }

    /**
     * @return array{title: string, description: string, robots: string}
     */
    protected function noIndexSeo(string $title, string $description): array
    {
        return [
            ...$this->basicSeo($title, $description),
            'robots' => 'noindex, nofollow',
        ];
    }
}

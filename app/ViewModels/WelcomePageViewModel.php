<?php

declare(strict_types=1);

namespace App\ViewModels;

use Illuminate\Support\Facades\Config;
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
        return [
            'canRegister' => $this->canRegister,
            'seo' => $this->basicSeo('ホーム', Config::string('app.description')),
        ];
    }
}

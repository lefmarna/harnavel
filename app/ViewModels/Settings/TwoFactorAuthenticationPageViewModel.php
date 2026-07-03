<?php

declare(strict_types=1);

namespace App\ViewModels\Settings;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class TwoFactorAuthenticationPageViewModel extends PageViewModel
{
    public function __construct(
        private bool $twoFactorEnabled,
        private bool $requiresConfirmation,
    ) {}

    /**
     * @return array{
     *     twoFactorEnabled: bool,
     *     requiresConfirmation: bool,
     *     seo: array{title: string, description: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'twoFactorEnabled' => $this->twoFactorEnabled,
            'requiresConfirmation' => $this->requiresConfirmation,
            'seo' => $this->basicSeo('二段階認証設定', '二段階認証の設定を管理します。'),
        ];
    }
}

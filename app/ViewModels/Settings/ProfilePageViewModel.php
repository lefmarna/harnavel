<?php

declare(strict_types=1);

namespace App\ViewModels\Settings;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class ProfilePageViewModel extends PageViewModel
{
    public function __construct(
        private bool $mustVerifyEmail,
        private ?string $status,
    ) {}

    /**
     * @return array{
     *     mustVerifyEmail: bool,
     *     status: string|null,
     *     seo: array{title: string, description: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'mustVerifyEmail' => $this->mustVerifyEmail,
            'status' => $this->status,
            'seo' => $this->basicSeo('プロフィール設定', '名前とメールアドレスを管理します。'),
        ];
    }
}

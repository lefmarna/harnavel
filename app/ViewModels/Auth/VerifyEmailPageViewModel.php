<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class VerifyEmailPageViewModel extends PageViewModel
{
    public function __construct(
        private ?string $status,
    ) {}

    /**
     * @return array{
     *     status: string|null,
     *     seo: array{title: string, description: string, robots: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'status' => $this->status,
            'seo' => $this->noIndexSeo('メール認証', 'メールアドレスの認証を完了してください。'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class GoogleLinkVerifyPageViewModel extends PageViewModel
{
    public function __construct(
        private string $status,
        private ?string $actionUrl = null,
        private ?string $email = null,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     actionUrl?: string,
     *     email?: string,
     *     seo: array{title: string, description: string, robots: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        [$title, $description] = match ($this->status) {
            'success' => ['Googleログイン連携完了', 'Googleログイン連携が完了しました。'],
            'confirm' => ['Googleログイン連携の確認', 'Googleログイン連携を確認してください。'],
            default => ['Googleログイン連携エラー', 'Googleログイン連携に失敗しました。'],
        };

        $props = [
            'status' => $this->status,
        ];

        if ($this->actionUrl !== null) {
            $props['actionUrl'] = $this->actionUrl;
        }

        if ($this->email !== null) {
            $props['email'] = $this->email;
        }

        $props['seo'] = $this->noIndexSeo($title, $description);

        return $props;
    }
}

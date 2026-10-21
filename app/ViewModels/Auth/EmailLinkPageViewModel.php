<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class EmailLinkPageViewModel extends PageViewModel
{
    /**
     * @param  string  $copyKey  confirm・success・error ごとの title と description を持つ翻訳キー
     * @param  'confirm'|'success'|'error'  $status
     */
    public function __construct(
        private string $copyKey,
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
        $props = ['status' => $this->status];

        if ($this->actionUrl !== null) {
            $props['actionUrl'] = $this->actionUrl;
        }

        if ($this->email !== null) {
            $props['email'] = $this->email;
        }

        $props['seo'] = $this->noIndexSeo(
            __("{$this->copyKey}.{$this->status}.title"),
            __("{$this->copyKey}.{$this->status}.description"),
        );

        return $props;
    }
}

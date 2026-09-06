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
        $key = match ($this->status) {
            'success', 'confirm' => $this->status,
            default => 'error',
        };

        $title = __("pages.auth.google_link.{$key}.title");
        $description = __("pages.auth.google_link.{$key}.description");

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

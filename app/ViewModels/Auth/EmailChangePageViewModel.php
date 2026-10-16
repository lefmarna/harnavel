<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class EmailChangePageViewModel extends PageViewModel
{
    /**
     * @param  'confirm'|'success'|'error'  $status
     */
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
        $props = ['status' => $this->status];

        if ($this->actionUrl !== null) {
            $props['actionUrl'] = $this->actionUrl;
        }

        if ($this->email !== null) {
            $props['email'] = $this->email;
        }

        $props['seo'] = $this->noIndexSeo(
            __("pages.auth.email_change.verify.{$this->status}.title"),
            __("pages.auth.email_change.verify.{$this->status}.description"),
        );

        return $props;
    }
}

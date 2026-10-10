<?php

declare(strict_types=1);

namespace App\ViewModels\Settings;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class SecurityPageViewModel extends PageViewModel
{
    /**
     * @param  list<array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>  $passkeys
     */
    public function __construct(
        private bool $canManagePasskeys,
        private array $passkeys,
        private bool $googleLinked,
        private bool $canUnlinkGoogle,
    ) {}

    /**
     * @return array{
     *     canManagePasskeys: bool,
     *     passkeys: list<array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>,
     *     googleLinked: bool,
     *     canUnlinkGoogle: bool,
     *     seo: array{title: string, description: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'canManagePasskeys' => $this->canManagePasskeys,
            'passkeys' => $this->passkeys,
            'googleLinked' => $this->googleLinked,
            'canUnlinkGoogle' => $this->canUnlinkGoogle,
            'seo' => $this->basicSeo(__('pages.settings.security.title'), __('pages.settings.security.description')),
        ];
    }
}

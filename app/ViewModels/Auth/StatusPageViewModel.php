<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

/**
 * セッションの status フラッシュだけを props に持つ noindex な認証ページ用 ViewModel。
 *
 * パスワード再設定リンク送信・メール認証など、コピーだけが違う複数ページで共有する。
 */
final readonly class StatusPageViewModel extends PageViewModel
{
    public function __construct(
        private string $title,
        private string $description,
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
            'seo' => $this->noIndexSeo($this->title, $this->description),
        ];
    }
}

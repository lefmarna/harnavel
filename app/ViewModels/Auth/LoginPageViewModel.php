<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\ViewModels\PageViewModel;
use Inertia\RenderContext;

final readonly class LoginPageViewModel extends PageViewModel
{
    public function __construct(
        private bool $canResetPassword,
        private bool $canRegister,
        private bool $canGoogleLogin,
        private ?string $error,
        private ?string $status,
    ) {}

    /**
     * @return array{
     *     canResetPassword: bool,
     *     canRegister: bool,
     *     canGoogleLogin: bool,
     *     error: string|null,
     *     status: string|null,
     *     seo: array{title: string, description: string, robots: string}
     * }
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'canResetPassword' => $this->canResetPassword,
            'canRegister' => $this->canRegister,
            'canGoogleLogin' => $this->canGoogleLogin,
            'error' => $this->error,
            'status' => $this->status,
            'seo' => $this->noIndexSeo('ログイン', 'メールアドレスとパスワードを入力してログインします。'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Account\EmailChangeService;
use App\Support\SignedPayloadLink;
use App\ViewModels\Auth\EmailChangePageViewModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * メールで届いたリンクの画面。メールソフトの先読みで実行されないよう、GET では確認画面だけを返し、POST で実行する。
 * リンクを受け取れたことがアドレスの所有を示すので、ログインは求めない。
 */
class EmailChangeController extends Controller
{
    public function __construct(private readonly EmailChangeService $emailChangeService) {}

    public function verify(Request $request): Response
    {
        /** @var array{from: string, to: string, state: string}|null $payload */
        $payload = SignedPayloadLink::read($request, ['from', 'to', 'state']);

        return $payload === null
            ? $this->page('verify', 'error')
            : $this->page('verify', 'confirm', $request->fullUrl(), $payload['to']);
    }

    public function complete(Request $request, string $userId): Response
    {
        /** @var array{from: string, to: string, state: string}|null $payload */
        $payload = SignedPayloadLink::read($request, ['from', 'to', 'state']);

        $confirmed = $payload !== null
            && $this->emailChangeService->confirm($userId, $payload['from'], $payload['to'], $payload['state']);

        return $this->page('verify', $confirmed ? 'success' : 'error');
    }

    public function revert(Request $request): Response
    {
        /** @var array{email: string}|null $payload */
        $payload = SignedPayloadLink::read($request, ['email']);

        return $payload === null
            ? $this->page('revert', 'error')
            : $this->page('revert', 'confirm', $request->fullUrl(), $payload['email']);
    }

    public function completeRevert(Request $request, string $userId): Response
    {
        /** @var array{email: string}|null $payload */
        $payload = SignedPayloadLink::read($request, ['email']);

        $reverted = $payload !== null
            && $this->emailChangeService->revert($userId, $payload['email']);

        return $this->page('revert', $reverted ? 'success' : 'error');
    }

    /**
     * @param  'verify'|'revert'  $action
     * @param  'confirm'|'success'|'error'  $status
     */
    private function page(string $action, string $status, ?string $actionUrl = null, ?string $email = null): Response
    {
        return Inertia::render(
            "auth/email-change/{$action}",
            new EmailChangePageViewModel($action, $status, $actionUrl, $email),
        );
    }
}

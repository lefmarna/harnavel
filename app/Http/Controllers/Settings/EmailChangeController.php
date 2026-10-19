<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Account\EmailChangeService;
use App\Support\SignedPayloadLink;
use App\ViewModels\Auth\EmailChangePageViewModel;
use Illuminate\Http\RedirectResponse;
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
            ? $this->page('error')
            : $this->page('confirm', $request->fullUrl(), $payload['to']);
    }

    public function complete(Request $request, string $userId): RedirectResponse
    {
        /** @var array{from: string, to: string, state: string}|null $payload */
        $payload = SignedPayloadLink::read($request, ['from', 'to', 'state']);

        $confirmed = $payload !== null
            && $this->emailChangeService->confirm($userId, $payload['from'], $payload['to'], $payload['state']);

        return redirect()->route($confirmed ? 'email-change.completed' : 'email-change.failed');
    }

    public function completed(): Response
    {
        return $this->page('success');
    }

    public function failed(): Response
    {
        return $this->page('error');
    }

    /**
     * @param  'confirm'|'success'|'error'  $status
     */
    private function page(string $status, ?string $actionUrl = null, ?string $email = null): Response
    {
        return Inertia::render('auth/email-change/verify', new EmailChangePageViewModel($status, $actionUrl, $email));
    }
}

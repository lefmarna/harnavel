<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectRevokedSessions;
use App\Http\Middleware\RequirePasswordWhenSet;
use App\Http\Middleware\RotateSessionOnConfirmation;
use App\Services\Observability\ProductionExceptionNotifier;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// ログのスタックトレースに、パスワードなどの引数の先頭を残さない。php.ini はサーバーごとに違うため、ここで揃える。
ini_set('zend.exception_ignore_args', '1');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 最外側へ置く。末尾に置くとメンテナンスの 503、POST サイズ超過、パス検査が
        // 先に応答を返し、エラー応答にだけヘッダーが付かない（TADR-0012）。
        $middleware->prepend(AddSecurityHeaders::class);

        // 偽の Host からパスワード再設定リンクなどの URL を作らせない。local とテストでは Laravel が無効にする。
        // サブドメインも受け付けない。DNS が向いていない名前や乗っ取られた名前へリンクを向けさせないため。
        // at を渡さないと Laravel は subdomains を無視してサブドメインを許可する。
        $middleware->trustHosts(
            at: fn (): array => ['^'.preg_quote((string) parse_url(Config::string('app.url'), PHP_URL_HOST)).'$'],
            subdomains: false,
        );

        $middleware->alias(['password.confirm' => RequirePasswordWhenSet::class]);

        // 失効したセッションを、ログインの要否を判定する auth より先にゲストへ戻す（TADR-0016）。
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RejectRevokedSessions::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(
            prepend: [
                AssignRequestId::class,
            ],
            append: [
                RejectRevokedSessions::class,
                RotateSessionOnConfirmation::class,
                HandleAppearance::class,
                HandleInertiaRequests::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->stopIgnoring(HttpException::class);
        $exceptions->dontReportWhen(
            fn (Throwable $e): bool => $e instanceof HttpExceptionInterface
                && $e->getStatusCode() < 500
                && ! $e instanceof ThrottleRequestsException,
        );

        // 総当たりや大量登録の兆候として残し、Discord にも知らせる。スタックトレースは要らないので既定の記録は止める。
        $exceptions->report(function (ThrottleRequestsException $e): bool {
            Log::warning('Request was rate limited.');
            app(ProductionExceptionNotifier::class)->report($e, app()->bound('request') ? request() : null);

            return false;
        });

        $exceptions->report(function (Throwable $e): void {
            app(ProductionExceptionNotifier::class)->report(
                $e,
                app()->bound('request') ? request() : null,
            );
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // フルページロードの 500 は Blade（TADR-0004）のまま返し、Vite と JS に依存させない。
            // X-Inertia があれば JS はすでに動いているので、Inertia ページで返してよい。
            // デバッグ時は Blade 500 と同じく変換せず、Ignition の応答を残す。
            if ($status === 500 && $request->header('X-Inertia') && ! config('app.debug')) {
                $requestId = $request->attributes->get('request_id');

                return Inertia::render('errors/server-error', [
                    'requestId' => is_string($requestId) && $requestId !== '' ? $requestId : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode(500);
            }

            if (! $request->header('X-Inertia')) {
                return $response;
            }

            if ($status === 419) {
                return back()->withErrors([
                    'page_expired' => __('errors.page_expired'),
                ]);
            }

            return $response;
        });
    })->create();

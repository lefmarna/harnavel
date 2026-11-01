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
use App\ViewModels\StaticPageViewModel;
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

// php.ini はサーバーごとに違うため、スタックトレースに引数を残さない設定をここで揃える。
ini_set('zend.exception_ignore_args', '1');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // メンテナンスの 503 や POST サイズ超過など、先に返る応答にもヘッダーを付けるため最外側に置く（TADR-0012）。
        $middleware->prepend(AddSecurityHeaders::class);

        // 偽の Host から再設定リンクなどの URL を作らせない。at を渡さないと Laravel は subdomains を無視する。
        $middleware->trustHosts(
            at: fn (): array => ['^'.preg_quote((string) parse_url(Config::string('app.url'), PHP_URL_HOST)).'$'],
            subdomains: false,
        );

        $middleware->alias(['password.confirm' => RequirePasswordWhenSet::class]);

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

            if ($status === 429 && $request->header('X-Inertia') && ! $request->isMethod('GET')) {
                return back()->withErrors([
                    'too_many_requests' => __('pages.errors.too_many_requests.description'),
                ]);
            }

            $errorPage = [403 => 'forbidden', 404 => 'not_found', 429 => 'too_many_requests'][$status] ?? null;

            if ($errorPage !== null && $request->hasSession() && ! ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                // ThrottleRequests は HandleInertiaRequests より先に拒否するため、429 では版の照合と共有データを補う。
                if (Inertia::getShared('currentUrl') === null) {
                    $inertia = app(HandleInertiaRequests::class);
                    $version = (string) $inertia->version($request);

                    if ($request->header('X-Inertia') && $request->isMethod('GET')
                        && $request->header('X-Inertia-Version', '') !== $version) {
                        return Inertia::location($request->fullUrl());
                    }

                    Inertia::version($version);
                    Inertia::share($inertia->share($request));
                }

                $page = Inertia::render(
                    'errors/'.str_replace('_', '-', $errorPage),
                    new StaticPageViewModel(
                        title: __("pages.errors.{$errorPage}.title"),
                        description: __("pages.errors.{$errorPage}.description"),
                        noIndex: true,
                    ),
                )
                    ->toResponse($request)
                    ->setStatusCode($status);

                if ($response->headers->has('Retry-After')) {
                    $page->headers->set('Retry-After', $response->headers->get('Retry-After'));
                }

                return $page;
            }

            // フルページロードの 500 は Vite や JS に依存しない Blade のまま返す（TADR-0004）。
            if ($status === 500 && $request->header('X-Inertia') && ! config('app.debug')) {
                $requestId = $request->attributes->get('request_id');

                return Inertia::render('errors/server-error', [
                    'requestId' => is_string($requestId) && $requestId !== '' ? $requestId : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode(500);
            }

            if ($status === 419 && $request->header('X-Inertia')) {
                return back()->withErrors([
                    'page_expired' => __('errors.page_expired'),
                ]);
            }

            return $response;
        });
    })->create();

<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Observability\ProductionExceptionNotifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(
            prepend: [
                AssignRequestId::class,
            ],
            append: [
                HandleAppearance::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->stopIgnoring(HttpException::class);
        $exceptions->dontReportWhen(
            fn (Throwable $e): bool => $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500,
        );

        $exceptions->report(function (Throwable $e): void {
            app(ProductionExceptionNotifier::class)->report(
                $e,
                app()->bound('request') ? request() : null,
            );
        });

        // Inertiaリクエストに対するHTTPエラーをハンドリング
        // セッション切れ(419)やサーバーエラー(500)などをユーザーフレンドリーに処理
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // Inertiaリクエストでない場合はデフォルトのレスポンスを返す
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            $status = $response->getStatusCode();

            // 419: CSRF Token Mismatch / Session Expired
            if ($status === 419) {
                return back()->withErrors([
                    'page_expired' => 'ページの有効期限が切れました。再度お試しください。',
                ]);
            }

            return $response;
        });
    })->create();

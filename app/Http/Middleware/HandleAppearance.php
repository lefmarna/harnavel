<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    private const APPEARANCES = ['light', 'dark', 'system'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cookie は暗号化していないため、インラインスクリプトへ渡す前に既知の値へ絞る。
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, self::APPEARANCES, true) ? $appearance : 'system');

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;
use Laravel\Passkeys\Passkeys;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $analyticsTrackingId = config('services.google.analytics_tracking_id');
        $adsensePublisherId = config('services.google.adsense_publisher_id');
        $trackingAllowed = app()->environment(['production', 'develop']);
        $adsTestMode = app()->environment('develop');

        $tracking = [
            'trackingEnabled' => $trackingAllowed && is_string($analyticsTrackingId) && $analyticsTrackingId !== '',
            'adsEnabled' => $trackingAllowed && is_string($adsensePublisherId) && $adsensePublisherId !== '',
            'adsensePublisherId' => is_string($adsensePublisherId) && $adsensePublisherId !== '' ? $adsensePublisherId : null,
            'adsTestMode' => $adsTestMode,
        ];
        $defaultRobots = app()->environment('production')
            ? 'max-image-preview:large'
            : 'noindex, nofollow';

        View::share('tracking', $tracking);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'url' => $request->root(),
            'currentUrl' => $request->url(),
            'appDisplayName' => config('app.display_name', config('app.name', 'Laravel')),
            'appDescription' => config('app.description', ''),
            'defaultRobots' => $defaultRobots,
            'passkeyAllowedOrigins' => Passkeys::allowedOrigins(),
            'seo' => [
                'title' => null,
                'description' => null,
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => null,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
                'hasPassword' => $user?->password !== null,
            ],
            'tracking' => $tracking,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}

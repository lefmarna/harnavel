<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PasskeyBrowserOrigins;
use App\Support\SensitiveUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;
use Laravel\Passkeys\Passkeys;
use RuntimeException;

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
        $version = parent::version($request);

        // 秘密を含む画面は計測スクリプトなしで描画される。版を分けておけば、そこからの Inertia 遷移は
        // 版の不一致で全体を読み込み直し、通常画面でスクリプトを読み込める。
        if ($this->trackingScriptsConfigured() && SensitiveUrl::carriesSecret($request)) {
            return $version.'|untracked';
        }

        return $version;
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

        $analyticsTrackingId = $this->configuredString('services.google.analytics_tracking_id');
        $adsensePublisherId = $this->configuredString('services.google.adsense_publisher_id');
        // 秘密を含む URL は計測スクリプトが location ごと外部へ送るため、その画面では読み込まない。
        $trackingAllowed = $this->trackingEnvironment() && ! SensitiveUrl::carriesSecret($request);
        $adsTestMode = app()->environment('develop');

        $tracking = [
            'trackingEnabled' => $trackingAllowed && $analyticsTrackingId !== null,
            'adsEnabled' => $trackingAllowed && $adsensePublisherId !== null,
            'adsensePublisherId' => $adsensePublisherId,
            'adsTestMode' => $adsTestMode,
        ];
        $defaultRobots = app()->environment('production')
            ? 'max-image-preview:large'
            : 'noindex, nofollow';

        View::share('tracking', $tracking);

        try {
            $allowedOrigins = Passkeys::allowedOrigins();
        } catch (RuntimeException) {
            // パスキー設定の欠落で、ほかの画面まで表示不能にしない。
            $allowedOrigins = [];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'url' => $request->root(),
            'currentUrl' => $request->url(),
            'appDisplayName' => config('app.display_name', config('app.name', 'Laravel')),
            'appDescription' => config('app.description', ''),
            'defaultRobots' => $defaultRobots,
            'passkeyAllowedOrigins' => PasskeyBrowserOrigins::from($allowedOrigins),
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

    private function trackingEnvironment(): bool
    {
        return app()->environment(['production', 'develop']);
    }

    private function trackingScriptsConfigured(): bool
    {
        return $this->trackingEnvironment()
            && ($this->configuredString('services.google.analytics_tracking_id') !== null
                || $this->configuredString('services.google.adsense_publisher_id') !== null);
    }

    private function configuredString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

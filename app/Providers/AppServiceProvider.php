<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ShareAuthenticatedUserLogContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerEventListeners();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Docker assigns Nginx a dynamic address. Trust it only while Nginx is loopback-bound
        // and the FPM service remains unpublished.
        if (config('app.trust_local_reverse_proxy')) {
            TrustProxies::at('REMOTE_ADDR');
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_PROTO);
        }

        Password::defaults(
            fn (): Password => Password::min(8)
                ->max(64)
                ->letters()
                ->numbers()
                ->uncompromised()
                ->rules(['regex:/^[\x21-\x7E]+\z/'])
        );
    }

    /**
     * Register framework event listeners.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(Authenticated::class, ShareAuthenticatedUserLogContext::class);
    }
}

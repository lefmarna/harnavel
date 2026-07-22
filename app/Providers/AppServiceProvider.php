<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ShareAuthenticatedUserLogContext;
use App\Support\OpcacheRevalidation;
use App\Support\ReleaseIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // リリース同一性の観測値は「収集」と「判定」を分離する。ここは収集側の adapter を
        // 差し替え可能な形で束ねるだけで、判定は ReleaseVerification が担う（TADR-0006）。
        $this->app->singleton(ReleaseIdentity::class, static fn (): ReleaseIdentity => ReleaseIdentity::fromFrontController());
        $this->app->singleton(OpcacheRevalidation::class, static fn (): OpcacheRevalidation => OpcacheRevalidation::fromRuntime());
    }

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

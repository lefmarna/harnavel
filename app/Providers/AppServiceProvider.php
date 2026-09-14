<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RequirePasswordWhenSet;
use App\Listeners\ShareAuthenticatedUserLogContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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
        $this->app->bind(RequirePasswordWhenSet::class, function (Application $app): RequirePasswordWhenSet {
            $timeout = Config::get('auth.password_timeout');

            return new RequirePasswordWhenSet(
                $app->make(ResponseFactory::class),
                $app->make(UrlGenerator::class),
                is_numeric($timeout) ? (int) $timeout : null,
            );
        });
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

        // DockerのNginxはアドレスが固定されない。Nginxがloopbackに閉じ、FPMが未公開の間だけ信頼する。
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

    protected function registerEventListeners(): void
    {
        Event::listen(Authenticated::class, ShareAuthenticatedUserLogContext::class);
    }
}

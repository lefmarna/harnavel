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
use RuntimeException;

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
        $this->configurePasskeySecret();
        $this->configureDefaults();
        $this->registerEventListeners();
    }

    /**
     * 秘密鍵が APP_KEY へ黙って倒れたままだと、鍵のローテーションで全パスキーが失効する（TADR-0013）。
     */
    private function configurePasskeySecret(): void
    {
        if (is_string(config('fortify.passkeys.user_handle_secret'))) {
            return;
        }

        if ($this->app->isProduction()) {
            throw new RuntimeException('PASSKEYS_USER_HANDLE_SECRET must be set in production.');
        }

        Config::set('passkeys.user_handle_secret', config('app.key'));
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

        // DockerのNginxはアドレスが固定されない。ローカルのComposeはNginxをloopbackに閉じ、FPMを公開しないため信頼する。
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

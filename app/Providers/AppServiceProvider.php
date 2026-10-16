<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Passkeys\DeletePasskey;
use App\Actions\Passkeys\StorePasskey;
use App\Http\Middleware\RequirePasswordWhenSet;
use App\Listeners\LogSecurityEvents;
use App\Listeners\ShareAuthenticatedUserLogContext;
use App\Models\User;
use App\Services\Account\SecurityNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Actions\DeletePasskey as BaseDeletePasskey;
use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Events\PasskeyRegistered;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BaseStorePasskey::class, StorePasskey::class);
        $this->app->bind(BaseDeletePasskey::class, DeletePasskey::class);

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
        $this->ensureDebugDisabledOnPublicHosts();
        $this->configurePasskeySecret();
        $this->configureDefaults();
        $this->registerEventListeners();
    }

    /**
     * 例外画面から環境変数やスタックトレースを公開したまま運用しない。develop も公開ホストに置く。
     */
    private function ensureDebugDisabledOnPublicHosts(): void
    {
        if ($this->app->environment(['production', 'develop']) && config('app.debug')) {
            // HTTP では起動中の例外も例外ハンドラーが描画するため、先に詳細表示を止めてから拒否する。
            Config::set('app.debug', false);

            throw new RuntimeException('APP_DEBUG must be disabled in production and develop.');
        }
    }

    /**
     * APP_KEY のローテーションで新規登録時のユーザーハンドルが変わらないよう、秘密鍵を分離する（TADR-0013）。
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
                ->uncompromised()
                ->rules(['regex:/^[\x21-\x7E]+\z/'])
        );
    }

    protected function registerEventListeners(): void
    {
        Event::listen(Authenticated::class, ShareAuthenticatedUserLogContext::class);
        Event::subscribe(LogSecurityEvents::class);

        // 照合に使ったユーザーの世代を残す。照合と失効が重なれば失効前の世代が残り、次のリクエストで弾かれる（TADR-0016）。
        Event::listen(function (Login $event): void {
            /** @var User $user 認証するのは users テーブルだけ。 */
            $user = $event->user;
            $user->recordSessionGeneration(app(Session::class));
        });

        Event::listen(function (PasskeyRegistered $event): void {
            /** @var User $user 認証するのは users テーブルだけ。 */
            $user = $event->user;
            app(SecurityNotifier::class)->notify($user, 'passkey_added');
        });
    }
}

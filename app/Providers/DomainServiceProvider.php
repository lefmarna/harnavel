<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Serviceクラスの依存関係を定義するProvider
 * サービスを追加するたびにPREFIXES定数に追記してください。
 *
 * NOTE: 全てのServiceはステートレスであることを前提とし、Singletonとして登録されます。
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * バインド対象のサービス名プレフィックスリスト
     * 例: 'User' -> UserServiceContract / UserService
     *
     * @var array<string>
     */
    private const PREFIXES = [
        'GoogleAuth',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ($this->getPrefixes() as $prefix) {
            $this->app->singleton(
                "App\Services\\{$prefix}\\{$prefix}ServiceContract",
                "App\Services\\{$prefix}\\{$prefix}Service"
            );
        }
    }

    /**
     * @return array<string>
     */
    protected function getPrefixes(): array
    {
        return self::PREFIXES;
    }
}

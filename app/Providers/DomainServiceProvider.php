<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * singleton として共有するため、サービスにリクエスト固有の状態を持たせない。
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

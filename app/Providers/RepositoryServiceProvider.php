<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * singleton として共有するため、リポジトリにリクエスト固有の状態を持たせない。
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    private const MODELS = [
        'User',
    ];

    public function register(): void
    {
        foreach (self::MODELS as $model) {
            $this->app->singleton(
                "App\Repositories\\{$model}\\{$model}RepositoryContract",
                "App\Repositories\\{$model}\\Eloquent{$model}Repository"
            );
        }
    }
}

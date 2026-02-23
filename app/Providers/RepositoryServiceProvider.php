<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Repositoryクラスの依存関係を定義するProvider
 * モデルを追加するたびにMODELS定数に追記してください。
 *
 * NOTE: 全てのRepositoryはステートレスであることを前提とし、Singletonとして登録されます。
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<string>
     */
    private const MODELS = [
        // 'User', // 初回はコメントアウトか、User実装後に有効化
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ($this->getModels() as $model) {
            $this->app->singleton(
                "App\Repositories\\{$model}\\{$model}RepositoryContract",
                "App\Repositories\\{$model}\\Eloquent{$model}Repository"
            );
        }
    }

    /**
     * @return array<string>
     */
    protected function getModels(): array
    {
        return self::MODELS;
    }
}

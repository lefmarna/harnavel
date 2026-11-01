<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ContractServiceProvider extends ServiceProvider
{
    private const array SERVICES = [
        'GoogleAuth',
    ];

    private const array REPOSITORIES = [
        'User',
    ];

    public function register(): void
    {
        foreach (self::SERVICES as $name) {
            $this->app->singleton(
                "App\Services\\{$name}\\{$name}ServiceContract",
                "App\Services\\{$name}\\{$name}Service"
            );
        }

        foreach (self::REPOSITORIES as $name) {
            $this->app->singleton(
                "App\Repositories\\{$name}\\{$name}RepositoryContract",
                "App\Repositories\\{$name}\\Eloquent{$name}Repository"
            );
        }
    }
}

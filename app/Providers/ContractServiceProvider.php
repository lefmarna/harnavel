<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ContractServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    private const SERVICES = [
        'GoogleAuth',
    ];

    /**
     * @var list<string>
     */
    private const REPOSITORIES = [
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

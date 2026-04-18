<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    DomainServiceProvider::class,
    RepositoryServiceProvider::class,
];

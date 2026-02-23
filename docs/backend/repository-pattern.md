# Repository Pattern

This document defines the data access layer (Repository) and business logic layer (Service) separation standards for this project.

## 1. Architecture Overview

This project adopts the **Repository Pattern** to avoid direct Eloquent model access. This separates data access logic, improving testability and maintainability. Service and Repository layers resolve dependencies through interfaces (Contracts).

### Layer Structure

1. **Controller**: Receives requests, calls Services, returns responses.
2. **Service**: Handles business logic. Depends on Repository interfaces.
3. **Repository**: Handles data retrieval, storage, and updates via Eloquent.

## 2. Directory Structure & Naming

Organized by domain, with Contract (interface) and Concrete (implementation) classes in each directory.

### Repositories

`app/Repositories/{EntityName}/`

- **Interface**: `{EntityName}RepositoryContract.php`
- **Implementation**: `Eloquent{EntityName}Repository.php`

### Services

`app/Services/{EntityName}/`

- **Interface**: `{EntityName}ServiceContract.php`
- **Implementation**: `{EntityName}Service.php`

## 3. Implementation Details

### 3.1. Repository Interface (Contract)

- Namespace: `App\Repositories\{EntityName}`
- Enforce return type declarations (`strict_types=1`).
- Use dedicated Params classes (DTOs) for arguments where possible.

**Example: `app/Repositories/User/UserRepositoryContract.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Params\User\StoreUserParams;

interface UserRepositoryContract
{
    public function updateOrCreate(StoreUserParams $params): User;

    public function find(int $id): ?User;
}
```

### 3.2. Repository Implementation (Eloquent)

- Namespace: `App\Repositories\{EntityName}`
- Implements the Contract (`implements`).
- Uses Eloquent models for DB operations.
- Class name prefixed with `Eloquent`.

**Example: `app/Repositories/User/EloquentUserRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Params\User\StoreUserParams;

class EloquentUserRepository implements UserRepositoryContract
{
    /** @inheritDoc */
    public function updateOrCreate(StoreUserParams $params): User
    {
        return User::updateOrCreate(
            ['email' => $params->email],
            $params->toArray()
        );
    }

    /** @inheritDoc */
    public function find(int $id): ?User
    {
        return User::find($id);
    }
}
```

### 3.3. Service Injection

- Services inject **Repository Contracts (interfaces)**, not concrete implementations.
- Use `private readonly` properties.

**Example: `app/Services/User/UserService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Params\User\StoreUserParams;
use App\Repositories\User\UserRepositoryContract;

class UserService implements UserServiceContract
{
    public function __construct(
        private readonly UserRepositoryContract $userRepository
    ) {}

    public function someBusinessLogic(StoreUserParams $params): void
    {
        $user = $this->userRepository->updateOrCreate($params);
        // ... business logic
    }
}
```

## 4. Service Provider Registration

Register new Repositories and Services in their respective ServiceProviders.

### 4.1. Repository Registration (`RepositoryServiceProvider`)

Add the model name to the array in `app/Providers/RepositoryServiceProvider.php` for automatic binding:

```php
class RepositoryServiceProvider extends ServiceProvider
{
    private const MODELS = [
        'User',
        'Post', // add new entity here
    ];

    public function register(): void
    {
        foreach (self::MODELS as $model) {
            $this->app->singleton(
                "App\Repositories\{$model}\{$model}RepositoryContract",
                "App\Repositories\{$model}\Eloquent{$model}Repository"
            );
        }
    }
}
```

### 4.2. Service Registration (`DomainServiceProvider`)

Similarly, register service names in `app/Providers/DomainServiceProvider.php`:

```php
class DomainServiceProvider extends ServiceProvider
{
    private const PREFIXES = [
        'User',
        'Auth', // add new service here
    ];

    public function register(): void
    {
        foreach (self::PREFIXES as $prefix) {
            $this->app->singleton(
                "App\Services\{$prefix}\{$prefix}ServiceContract",
                "App\Services\{$prefix}\{$prefix}Service"
            );
        }
    }
}
```

## 5. Verification Checklist

- [ ] **Interface separation**: Contract created; Service depends on Contract (not implementation)
- [ ] **Type safety**: `declare(strict_types=1);` declared; arguments and return types specified
- [ ] **DTO usage**: Multiple parameters passed via `Params` class (not arrays)
- [ ] **Naming**: Follows `Eloquent{Entity}Repository` / `{Entity}RepositoryContract` convention
- [ ] **Dependency injection**: Uses DI container (never `new EloquentUserRepository()` directly)

# System Architecture & Implementation Guide

This document defines the system's design philosophy, architectural layers, and the specific **Repository Pattern** implementation rules.
It serves as the "source of truth" for code placement, dependency rules, and data access logic.

## 1. Architectural Style
**Domain-Driven Design (DDD) + Clean Architecture** on Laravel.

We avoid direct access to Eloquent models in controllers and adopt the **Repository Pattern**. This separates data access logic, improving testability and maintainability.

## 2. Layer Responsibilities

### Domain Layer (`app/Domain` or logic within Services)
**Pure business logic.** No framework dependencies (as much as possible).
- **Contains**: Entities, Value Objects, Domain Services.
- **Do**: Implement core business rules here.
- **Don't**: Reference Eloquent models directly (use interfaces) or Http classes.

### Application Layer (`app/Services`)
**Orchestration.** Connects UI/API to Domain.
- **Contains**: Service Contracts, Service Implementations, DTOs (Params).
- **Do**: Handle transactions, event dispatching, and coordinating domain objects.
- **Don't**: Write complex business logic here (delegate to Domain).

### Infrastructure Layer (`app/Repositories`)
**Technical implementation.**
- **Contains**: Repository Contracts, Eloquent Implementations.
- **Do**: Implement interfaces defined in Domain/Application.
- **Don't**: Leak implementation details to upper layers.

### Presentation Layer (`app/Http`, `resources/js`)
**User Interface.**
- **Contains**: Controllers, Inertia Pages, CLI Commands, Middleware.
- **Do**: Validate input (FormRequest), call Services, return responses.
- **Don't**: Contain ANY business logic or direct DB access.

---

## 3. Repository Pattern Implementation Rules

This section defines the implementation standards for separating the data access layer (Repository) and business logic layer (Service).

**Important:** All Services and Repositories are premised on being **stateless**. They are registered as **singletons** in the service container. Do not hold request-specific state in class properties.

### 3.1. Hierarchy
1.  **Controller**: Receives requests, calls Services, and returns responses.
2.  **Service**: Handles business logic. Depends on Repository Interfaces.
3.  **Repository**: Handles data retrieval, storage, and updates. Uses Eloquent.

### 3.2. Directory Structure and Naming Conventions

Create directories for each feature (Domain), and place Contracts (Interfaces) and Concretes (Implementation classes) within them.

#### Repositories
`app/Repositories/{EntityName}/`
- **Interface**: `{EntityName}RepositoryContract.php`
- **Implementation**: `Eloquent{EntityName}Repository.php`

#### Services
`app/Services/{EntityName}/`
- **Interface**: `{EntityName}ServiceContract.php`
- **Implementation**: `{EntityName}Service.php`

### 3.3. Implementation Details

#### 3.3.1. Repository Interface (`Contract`)
- Namespace: `App\Repositories\{EntityName}`
- Enforce return type definitions (`strict_types=1`).
- Use dedicated Params classes (DTOs) for arguments whenever possible.

**Example: `app/Repositories/User/UserRepositoryContract.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Params\V2\User\StoreUserParams;

interface UserRepositoryContract
{
    /**
     * Create or update a user
     *
     * @param StoreUserParams $params
     * @return User
     */
    public function updateOrCreate(StoreUserParams $params): User;

    /**
     * Find a user by ID
     *
     * @param string $id
     * @return User
     */
    public function findOrFail(string $id): User;
}
```

#### 3.3.2. Repository Implementation (`Eloquent`)
- Namespace: `App\Repositories\{EntityName}`
- Implement the Contract (`implements`).
- Use Eloquent models for DB operations.
- Add the `Eloquent` prefix to the class name.

**Example: `app/Repositories/User/EloquentUserRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use App\Params\V2\User\StoreUserParams;
use App\Repositories\User\UserRepositoryContract;

class EloquentUserRepository implements UserRepositoryContract
{
    /**
     * @inheritDoc
     */
    public function updateOrCreate(StoreUserParams $params): User
    {
        return User::updateOrCreate(
            ['email' => $params->email],
            $params->toArray()
        );
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(string $id): User
    {
        return User::findOrFail($id);
    }
}
```

#### 3.3.3. Injection into Service
- In Service classes, inject the **Repository Contract (Interface)** via constructor injection, not the concrete implementation class.
- Use `private readonly` properties.

**Example: `app/Services/User/UserService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Repositories\User\UserRepositoryContract;

class UserService implements UserServiceContract
{
    public function __construct(
        private readonly UserRepositoryContract $userRepository
    ) {}

    public function someBusinessLogic(string $userId): void
    {
        $user = $this->userRepository->findOrFail($userId);
        // ... business logic
    }
}
```

### 3.4. Registration in Service Providers

When you create a new Repository or Service, add the binding to the respective ServiceProvider.

#### 3.4.1. Registering a Repository (`RepositoryServiceProvider`)

In `app/Providers/RepositoryServiceProvider.php`, use the mechanism that automatically binds by adding the model name to the array (or manually bind).

```php
class RepositoryServiceProvider extends ServiceProvider
{
    private const MODELS = [
        'User',
        'Post', // Added
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
```

#### 3.4.2. Registering a Service (`DomainServiceProvider`)

Similarly, register the service name in `app/Providers/DomainServiceProvider.php`.

```php
class DomainServiceProvider extends ServiceProvider
{
    private const PREFIXES = [
        'User',
        'Auth', // Added
    ];

    public function register(): void
    {
        foreach (self::PREFIXES as $prefix) {
            $this->app->singleton(
                "App\Services\\{$prefix}\\{$prefix}ServiceContract",
                "App\Services\\{$prefix}\\{$prefix}Service"
            );
        }
    }
}
```

---

## 4. Verification Checklist

### Architectural Rules
- [ ] **Dependency Rule**: Does inner layer depend on outer layer? (Should be NO).
- [ ] **Business Logic**: Is logic inside Controller? (Should be moved to Domain/Application).
- [ ] **Naming**: Do classes follow `Noun + Responsibility` pattern?

### Repository Pattern Rules
- [ ] **Interface Separation**: Did you create a Contract (`{Entity}RepositoryContract`)?
- [ ] **Naming**: Does implementation class start with `Eloquent` (`Eloquent{Entity}Repository`)?
- [ ] **Type Safety**: Did you declare `declare(strict_types=1);` and specify types for arguments and return values?
- [ ] **DTOs**: Are you using `Params` classes (DTOs) instead of arrays when passing multiple parameters?
- [ ] **Dependency Injection**: Are you injecting Contracts, not concrete classes?
- [ ] **Registration**: Did you add the new entity to `RepositoryServiceProvider` / `DomainServiceProvider`?
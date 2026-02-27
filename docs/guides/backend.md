# Backend Guide

## Scope

Use this file for day-to-day backend implementation in Laravel.
For generated framework and ecosystem rules, see `docs/generated/boost.md`.
This file is the canonical source for backend rules not fully covered by Boost.

## Resolved Project Decisions

These decisions were fixed to remove ambiguity for AI and human contributors:

| Topic | Decision |
| --- | --- |
| Layer structure | `Controller -> Service -> Repository` |
| Service interface | Required (`{Domain}ServiceContract`) |
| Repository interface | Required (`{Domain}RepositoryContract`) |
| Route naming | Laravel resource-style action names |
| Params class | Use for non-trivial input transfer |
| Primary key policy | UUIDv7 for new application domain tables |
| Mass assignment | `fillable` first |
| PHPDoc | Required on public methods with behavior intent |
| Test title language | Japanese |
| Migration `after()` | Required when adding columns to existing tables |

## Coding Baseline

- Use `declare(strict_types=1);` in PHP source files.
- Use constructor property promotion with `private readonly` for injected dependencies.
- Do not keep commented-out code in commits.
- Prefer early returns and shallow nesting.
- Prefer `collect()` / collection methods (or `array_map` / `array_filter`) over `foreach` loops.

## Architecture Contract

Application flow is fixed:

`Controller -> Service -> Repository`

Rules:
- Controllers handle request/response only.
- Services contain business logic and transaction boundaries.
- Repositories own data access through Eloquent.
- Never query models directly in controllers. All data access goes through repositories.
- No queue workers: XServer has no queue daemon. All jobs use `dispatchSync()`.
- `config()` only: Never use `env()` outside `config/` files.

Service and repository interfaces are required:
- `{Domain}ServiceContract` + `{Domain}Service`
- `{Domain}RepositoryContract` + `Eloquent{Domain}Repository`

Directory structure baseline:

```text
app/
├── Http/
│   ├── Controllers/      # Controllers
│   ├── Requests/         # Form Request validation
│   └── Resources/        # API Resource responses
├── Services/
│   └── {Domain}/
│       └── Contracts/    # {Domain}ServiceContract
├── Repositories/
│   └── {Domain}/
│       └── Contracts/    # {Domain}RepositoryContract
└── Providers/            # RepositoryServiceProvider / DomainServiceProvider
```

Service provider bindings:
- Register repository bindings in `RepositoryServiceProvider`.
- Register service bindings in `DomainServiceProvider` (or equivalent domain provider).

### Implementation Templates

**Repository Interface** (`app/Repositories/User/UserRepositoryContract.php`):

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

**Repository Implementation** (`app/Repositories/User/EloquentUserRepository.php`):

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

**Service with DI** (`app/Services/User/UserService.php`):

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

**RepositoryServiceProvider** (`app/Providers/RepositoryServiceProvider.php`):

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

**DomainServiceProvider** (`app/Providers/DomainServiceProvider.php`):

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

## Routing and Naming

- Use Laravel resource-style action naming:
- `index`, `show`, `store`, `update`, `destroy`
- Use descriptive domain names for classes and methods.
- Prefer route path nouns and consistent domain vocabulary.

## Validation and Response

- Input: Form Request classes (`app/Http/Requests/`), never inline.
- Output: API Resources (`app/Http/Resources/`) or `Inertia::render()`.
- Keep status codes explicit and consistent with error type.
- JSON responses should use `Symfony\Component\HttpFoundation\Response` constants.

Exception-to-status baseline:

| Exception | Status | When |
| --- | --- | --- |
| `ValidationException` | `422` | Form Request validation fails |
| `AuthenticationException` | `401` | Unauthenticated |
| `AuthorizationException` | `403` | Policy denies access |
| `ModelNotFoundException` | `404` | Resource not found |
| `DomainException` | `400` | Business rule violation |
| `Throwable` | `500` | Server error |

Logging rules:
- ALWAYS include identifiers (`user_id`, `request_id`) in log context.
- Never log passwords, tokens, or PII.

```php
Log::error('Payment failed', [
    'user_id' => $user->id,
    'order_id' => $order->id,
    'reason' => $e->getMessage(),
]);
```

## API Error Contract (JSON Endpoints)

For JSON endpoints, keep a stable error shape:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human readable message",
    "errors": {
      "field": ["detail"]
    }
  }
}
```

Rules:
- Keep `code` machine-readable and stable.
- Keep `message` user-facing.
- Include `errors` for validation-like field issues.

## Authentication and Security Baseline

- Laravel Fortify with session cookies. Session-based via `web` guard.
- CSRF/session expiration (`419`) for Inertia requests must return a user-recoverable response (see `bootstrap/app.php` exception response handling).
- Two-factor authentication is enabled in `config/fortify.php` with confirmation enabled.
- Login and two-factor throttling are defined in `FortifyServiceProvider` with `5/min` limits.

## Model and Identifier Policy

Primary key strategy:
- New application domain models/tables use UUIDv7 as standard.
- Existing tables (including `users` and framework-managed tables) keep current key types until an explicit migration plan exists.
- In Laravel 12, UUIDv7 is implemented with `HasUuids` + UUID columns.
- Pure pivot tables may use composite keys when no dedicated Eloquent model is required.

Mass assignment policy:
- Prefer `fillable`.
- Use `guarded` only when there is a clear reason.

Model conventions:
- Cast boolean-like DB fields explicitly in `$casts`.
- Keep model responsibilities focused on persistence/domain behavior (no transport formatting).

## Params (DTO) Usage

Params classes should be used when:
- input has many fields
- mapping/normalization logic is non-trivial
- type-safe transfer between service and repository is needed

For simple single-field or trivial pass-through operations, direct typed arguments are acceptable.

## Database Rules

- Multi-step writes must be wrapped in `DB::transaction` (service layer).
- Prefer `Model::query()` over `DB::` facade (exception: `DB::transaction`).
- For pure many-to-many pivot tables, composite primary keys are recommended.
- Note: Eloquent models do not support composite primary keys. If a pivot needs model-like behavior, use an explicit surrogate key model pattern instead.
- When adding columns to existing MariaDB/MySQL tables, `after()` is required by project policy.
- When modifying a column, the migration must include ALL attributes previously defined on the column. Laravel 12 drops any unspecified attributes.

```php
// Bad - will drop the default value
$table->string('name')->nullable()->change();

// Good - preserves all attributes
$table->string('name')->default('')->nullable()->change();
```
- Keep FK type exactly aligned with referenced PK type (`uuid`, `bigint`, etc).
- Use column/index/constraint naming from `docs/references/schema-design-spec.md`.

## Testing Rules

- Use Pest for unit/feature tests and Playwright for E2E.
- Write or update tests in the same change as implementation.
- Test names in Japanese for readability in test output.
- Use factories for model data.
- Feature tests should be isolated and deterministic (`RefreshDatabase` where applicable).
- Use `Mail::fake()` / `Notification::fake()` for mail/notification assertions.
- Use datasets (`->with([...])`) for parameterized validation tests.
- Do not use fixed sleeps in tests.
- Base setup (`tests/Pest.php`): all Feature tests use `RefreshDatabase` (DB reset per test).

Layer responsibilities:

| Layer | Tool | Scope | Speed | Location |
| --- | --- | --- | --- | --- |
| Unit | Pest | Pure logic, helpers, calculations | Fast | `tests/Unit/` |
| Feature | Pest | API endpoints, DB, auth flows | Moderate | `tests/Feature/` |
| E2E | Playwright | Full user flows (browser) | Slow | `e2e/tests/` |

TDD baseline loop:
1. Write a failing test: `php artisan make:test --pest FeatureName`
2. Implement minimal code to pass
3. Refactor while keeping tests green
4. Run `php artisan test --filter=FeatureName` frequently

Minimum coverage expectation:
- happy path
- representative error path
- at least one boundary/edge case for branching logic

## Verification Checklist

- [ ] Layer boundaries (Controller/Service/Repository) are respected
- [ ] Service/Repository contracts are present
- [ ] Form Request + Resource response path is used
- [ ] JSON API error responses follow the standard contract
- [ ] Auth/rate-limit/CSRF security baseline remains aligned with Fortify settings
- [ ] UUIDv7 policy is applied for new application models
- [ ] `fillable` policy is followed
- [ ] DB changes include transaction, modifier preservation, and `after()` policy
- [ ] Public methods include intent-revealing PHPDoc
- [ ] Unit/Feature/E2E responsibilities are covered at the right layer
- [ ] Tests cover happy path, error path, and boundary case
- [ ] Tests were added/updated and pass

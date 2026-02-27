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

## Logging Safety

- Never log passwords, tokens, or PII.
- Always include identifiers (`user_id`, `request_id`) in log context.

## API Error Contract (JSON Endpoints)

Stable error shape for JSON responses:

```json
{ "success": false, "error": { "code": "MACHINE_READABLE", "message": "Human readable", "errors": { "field": ["detail"] } } }
```

Exception-to-status baseline:

| Exception | Status |
| --- | --- |
| `ValidationException` | `422` |
| `AuthenticationException` | `401` |
| `AuthorizationException` | `403` |
| `ModelNotFoundException` | `404` |
| `Throwable` | `500` |

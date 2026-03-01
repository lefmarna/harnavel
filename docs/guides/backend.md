# Backend Guide

## Scope

Use this file only for backend rules that are project-specific and not obvious from Laravel or `docs/generated/boost.md`.

## Architecture

- Fixed flow: `Controller -> Service -> Repository`
- Controllers own request/response only.
- Services own business logic and transaction boundaries.
- Repositories own Eloquent access.
- Do not query models directly in controllers.
- Service interfaces are required: `{Domain}ServiceContract`
- Repository interfaces are required: `{Domain}RepositoryContract` + `Eloquent{Domain}Repository`
- Use `config()`, never `env()` outside `config/`.
- No queue workers in this project profile. Use synchronous execution (`dispatchSync()` / `sync` queue connection).

## Input and Model Policy

- Prefer `fillable`.
- Use Params/DTO objects only when the input is non-trivial (many fields, normalization, or cross-layer transfer).
- For simple pass-through input, prefer direct typed arguments.
- Cast boolean-like database fields explicitly.

## API Error Contract (JSON Endpoints)

- JSON endpoints use this error envelope:

```json
{ "success": false, "error": { "code": "MACHINE_READABLE", "message": "Human readable", "errors": { "field": ["detail"] } } }
```

## Database Rules

- New application domain tables use UUIDv7. Existing framework tables keep their current key types until an explicit migration plan exists.
- Wrap multi-step writes in `DB::transaction` at the service layer.
- When adding columns to existing MariaDB/MySQL tables, `after()` is required.
- When using `->change()`, restate all existing modifiers so Laravel 12 does not drop attributes.
- Use `docs/references/schema-design-spec.md` for naming, FK type alignment, and index policy.

## Project Conventions

- Route names follow Laravel resource-style action names.
- Public methods require PHPDoc when behavior intent is not obvious.
- Test titles are written in Japanese.

## Exception Status Baseline

| Exception | Status |
| --- | --- |
| `ValidationException` | `422` |
| `AuthenticationException` | `401` |
| `AuthorizationException` | `403` |
| `ModelNotFoundException` | `404` |
| `Throwable` | `500` |

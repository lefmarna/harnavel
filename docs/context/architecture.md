# Architecture Context

## Why This Architecture

The template uses a Laravel monolith with Inertia to keep delivery speed high while preserving clear domain boundaries.

Primary goals:
- predictable layering
- testable business logic
- low cognitive load for multi-project reuse

## Layered Application Design

Request flow:

`HTTP -> Controller -> Service -> Repository -> Eloquent`

Responsibilities:
- Controller: transport concerns (request, auth gate, response shape)
- Service: business use cases, orchestration, transactions
- Repository: persistence and query composition

This structure is optimized for template reuse: implementation details can vary by project while contracts remain stable.

## Dependency Inversion

Service and repository layers are designed around contracts.
Concrete classes are bound through service providers.

Benefits:
- easier testing/mocking
- explicit architecture boundaries
- safe refactors in domain modules

## State and Rendering Strategy

- Backend is source of truth for domain state.
- Inertia props bridge backend state to frontend pages.
- Frontend keeps local UI state only.

## Operational Constraints Influencing Design

- Queue workers are out of scope in this template.
- Job execution is synchronous by policy.
- Config values must be accessed through `config()` outside config files.

## Data Access Principles

- Repositories encapsulate Eloquent access.
- Avoid ad hoc query logic in controllers/services.
- Favor explicit eager loading and query intent for N+1 prevention.

## Failure and Security Model

- Validation is centralized in Form Request classes.
- Error responses map exception categories to predictable HTTP status codes.
- Sensitive values are excluded from logs.

## Verification Checklist

- [ ] Layer responsibilities are not mixed
- [ ] Dependency direction stays inward (Controller -> Service -> Repository)
- [ ] Contracts are used at service/repository boundaries
- [ ] Operational constraints (no queue workers, sync jobs, config usage) are respected

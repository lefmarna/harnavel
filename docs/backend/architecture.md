# Backend Architecture

## Contents

- [Backend Architecture](#backend-architecture)
  - [Contents](#contents)
  - [3-Layer Pattern](#3-layer-pattern)
  - [Directory Structure](#directory-structure)
  - [Layer Rules](#layer-rules)
  - [Project-Specific Constraints](#project-specific-constraints)
  - [Verification Checklist](#verification-checklist)

## 3-Layer Pattern

```
HTTP Request
     │
     ▼
┌─────────────────────┐
│     Controller      │  FormRequest → Service → Response
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│      Service        │  Business logic, orchestrates repositories
│  (ServiceContract)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│    Repository       │  Data access only, abstracts Eloquent
│ (RepositoryContract)│
└─────────────────────┘
```

**Critical rule**: Never query models directly in controllers. All data access goes through repositories.

## Directory Structure

```
app/
├── Http/
│   ├── Controllers/           # Controllers
│   ├── Requests/              # Form Request validation
│   ├── Resources/             # API Resource responses
│   └── Middleware/
├── Services/                  # Business logic
│   └── Contracts/             # Service interfaces
├── Repositories/              # Data access
│   └── Contracts/             # Repository interfaces
├── Models/
├── Jobs/                      # Sync only (dispatchSync)
├── Mail/
├── Providers/                 # Binds contracts → implementations
└── Rules/
```

## Layer Rules

> Implementation templates and code examples: [repository-pattern.md](repository-pattern.md)

**Controller**:

- Pattern: `{Domain}Controller`
- Form Request for validation (never inline)
- API Resource or Inertia Render for response
- **No business logic**

**Service**:

- Pattern: `{Domain}ServiceContract` + `{Domain}Service`
- Coordinates repositories, encapsulates business logic
- Bound in `AppServiceProvider` or `DomainServiceProvider`

**Repository**:

- Pattern: `{Domain}RepositoryContract` + `Eloquent{Domain}Repository`
- Data access only (Eloquent queries)
- Bound in `RepositoryServiceProvider`

## Project-Specific Constraints

These are non-obvious rules specific to this project:

- **No queue workers**: XServer has no queue daemon. All jobs use `dispatchSync()`
- **`config()` only**: Never use `env()` outside `config/` files
- **`Model::query()`**: Prefer over `DB::` facade (exception: `DB::transaction`)
- **`collect()` over foreach**: Use collection methods or `array_map`/`array_filter`

For day-to-day coding rules, see [coding-guide.md](coding-guide.md). For test conventions, see [testing.md](testing.md).

## Verification Checklist

- [ ] **Data access**: No direct model queries in controllers or services (all through repositories)
- [ ] **Repository**: Contract + `Eloquent` prefixed implementation
- [ ] **Service**: Contract + implementation
- [ ] **Controller**: Form Request used (no inline validation)
- [ ] **Queue**: `dispatchSync()` used (not `dispatch()`)

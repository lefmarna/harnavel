# Laravel Boost Guidelines (Agent Rules)

## Project Context

This is a **Laravel Docker template** project. The Laravel application is deployed at the project root.

After setting up with Inertia + React + Tailwind, the following overrides apply:

- Inertia pages live in `resources/js/pages` (lowercase) per `resources/js/app.tsx`.
- Middleware classes exist in `app/Http/Middleware`; follow existing conventions there.
- Treat `composer.json` and `package.json` as the source of truth for version requirements.

---

## Laravel Boost

Laravel Boost is an MCP server that comes with powerful tools designed specifically for Laravel applications. Use them.

### Key Tools

| Tool                    | Purpose                                                                   |
| ----------------------- | ------------------------------------------------------------------------- |
| `application-info`      | Get PHP version, Laravel version, installed packages, and Eloquent models |
| `list-artisan-commands` | Check available Artisan commands and parameters                           |
| `get-absolute-url`      | Get correct scheme, domain/IP, and port for project URLs                  |
| `tinker`                | Execute PHP to debug code or query Eloquent models                        |
| `database-query`        | Read-only SQL queries                                                     |
| `database-schema`       | Read database schema (tables, columns, indexes, foreign keys)             |
| `browser-logs`          | Read browser console errors for frontend debugging                        |
| `search-docs`           | **Critically important** - Search version-specific Laravel ecosystem docs |
| `last-error`            | Get details of the last backend error/exception                           |

### Searching Documentation (Critically Important)

- **Always use `search-docs` before other approaches** for Laravel ecosystem documentation.
- Pass multiple, broad, simple queries: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared.
- The tool returns version-specific documentation based on installed packages.

---

## PHP Rules

- Always use curly braces for control structures, even for single lines.
- Use PHP 8 constructor property promotion in `__construct()`.
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.
- Prefer PHPDoc blocks over inline comments.
- Add useful array shape type definitions for arrays when appropriate.
- Enum keys should be TitleCase (e.g., `FavoritePerson`, `Monthly`).

---

## Test Enforcement

- Every change must be programmatically tested.
- Write a new test or update an existing test, then run the affected tests.
- Run minimum tests needed: `php artisan test` with specific filename or filter.

### Pest Testing

- All tests must be written using Pest: `php artisan make:test --pest {name}`.
- Tests should cover happy paths, failure paths, and edge cases.
- Tests live in `tests/Feature` and `tests/Unit` directories.
- Use factories for test data (check for custom states before manually setting up).
- Use datasets to simplify tests with duplicated data (especially validation rules).

### Running Tests

```bash
# All tests
php artisan test

# Single file
php artisan test tests/Feature/ExampleTest.php

# Filter by name
php artisan test --filter=testName
```

---

## Laravel Core Rules

- Use `php artisan make:` commands with `--no-interaction` for new files.
- Use Eloquent models and relationships over raw queries.
- Avoid `DB::`; prefer `Model::query()`.
- Use eager loading to prevent N+1 query problems.
- Create Form Request classes for validation (not inline in controllers).
- Use environment variables only in config files (use `config('app.name')`, not `env('APP_NAME')`).
- When generating links, prefer named routes and the `route()` function.

### Laravel 12 Structure

- No middleware files in `app/Http/Middleware/` (use `bootstrap/app.php`).
- `bootstrap/app.php` registers middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application-specific service providers.
- Commands in `app/Console/Commands/` auto-register.

---

## Code Formatting

- Run `vendor/bin/pint --dirty` before finalizing changes.
- Do not run `vendor/bin/pint --test`; just run `vendor/bin/pint` to fix issues.

---

## Inertia + React (After Integration)

The following rules apply after Inertia + React + Tailwind are added to the project.

### Inertia Core

- Use `Inertia::render()` for server-side routing instead of Blade views.
- Pages should be in `resources/js/pages` (check `vite.config.js` for configuration).
- Use `<Link>` or `router.visit()` for navigation.

### Forms

- Use the `<Form>` component from Inertia for forms.
- Alternatively use the `useForm` helper for programmatic control.

### Wayfinder

- Import route helpers from `@/actions/` and `@/routes/`.
- Prefer named imports for tree-shaking.
- Run `php artisan wayfinder:generate` after route changes if Vite plugin isn't installed.

---

## Tailwind CSS v4

- Use Tailwind CSS v4 conventions (do not use deprecated utilities).
- Configuration is CSS-first using `@theme` directive.
- Import Tailwind using `@import "tailwindcss";` (not `@tailwind` directives).
- Support dark mode using `dark:` prefix when existing components do.
- Use gap utilities for spacing in flex/grid layouts (not margins).

### Deprecated Utilities Replacements

| Deprecated          | Use Instead     |
| ------------------- | --------------- |
| `bg-opacity-*`      | `bg-black/*`    |
| `text-opacity-*`    | `text-black/*`  |
| `flex-shrink-*`     | `shrink-*`      |
| `flex-grow-*`       | `grow-*`        |
| `overflow-ellipsis` | `text-ellipsis` |

---

## Fortify (If Used)

- Check `config/fortify.php` to see enabled features.
- Use `search-docs` for detailed guidance on authentication features.
- Views can be customized in `FortifyServiceProvider::boot()`.
- Actions in `app/Actions/Fortify/` are fully customizable.

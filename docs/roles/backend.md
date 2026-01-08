You are an expert in Laravel, PHP, RESTful API design, and backend development with Inertia.js.

# Development Environment

- **Run Laravel/PHP commands via Docker**: `docker compose exec app <command>`
- **Prefer mise tasks** when available (e.g., `mise run migrate`, `mise run test`).

# Key Requirements

- Use PHP 8.2+ features (typed properties, match expressions, readonly properties)
- Follow PSR-12 coding standards
- **Strict typing is recommended**: `declare(strict_types=1);` in new files
- Prefer iteration and modularization over duplication
- Use Form Request classes in `app/Http/Requests` for validation
- Use database transactions (DB::transaction) for operations requiring data integrity
- Favor dependency injection via service containers
- Prefer Eloquent models and relationships over raw queries
- Jobs (`app/Jobs`) are allowed, but assume queue workers may be unavailable
- When using Jobs, provide a safe synchronous fallback (e.g., `dispatchSync`)

# Project Structure (Current)

- Controllers: `app/Http/Controllers`
- Requests: `app/Http/Requests`
- Jobs: `app/Jobs`
- Routes: `routes/web.php`, `routes/api.php`

# Project Structure (After Inertia Integration)

- Use `Inertia::render()` for server-driven pages (not Blade views)
- Follow Wayfinder conventions for route generation

# Tooling

- Use `php artisan make:*` with `--no-interaction` for new files.
- Run focused tests with `php artisan test --filter=...` or per file.
- Run `vendor/bin/pint --dirty` before finalizing changes.
- See `docs/agent/boost.md` for hard rules (testing, Pint, tooling).

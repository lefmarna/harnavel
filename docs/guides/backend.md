# Code Style

- No magic numbers; use named constants
- Use early returns to reduce nesting
- Keep nesting shallow (max 2-3 levels)
- Test function names in Japanese for readability

# Environment

- Run via Docker: `docker compose exec app bash`
- Prefer mise tasks when available (e.g., `mise run migrate`, `mise run test`)
- See `docs/agent/boost.md` for Laravel Boost rules

# Requirements

- PHP 8.2+, PSR-12, `declare(strict_types=1);` in all files
- Use Form Requests for validation
- Use `DB::transaction` for data integrity
- Prefer Eloquent over raw queries
- Jobs must run synchronously (`dispatchSync`) - no queue workers
- Use `collect()` methods (or `array_map`/`array_filter`) for data transformations instead of `foreach` loops
- **Deployment target**: XServer (shared hosting)
- **Cron scheduler**: Manual setup via XServer control panel

# Testing (TDD)

Follow **Test-Driven Development**: write tests first, then implement.

1. Write a failing test (`php artisan make:test --pest FeatureName`)
2. Implement minimal code to pass
3. Refactor while keeping tests green
4. Run `php artisan test --filter=FeatureName` frequently

# Structure

```
app/
├── Http/Controllers/   # Inertia controllers
├── Http/Requests/      # Form Request validation
├── Actions/Fortify/    # Auth actions
├── Models/             # Eloquent models
└── Jobs/               # Sync jobs only
routes/
├── web.php             # Inertia routes
├── auth.php            # Auth routes
└── settings.php        # Settings routes
tests/
├── Feature/
└── Unit/
```

# Inertia

Use `Inertia::render()` for pages. Pages in `resources/js/pages/`.
```php
return Inertia::render('dashboard', ['users' => User::paginate(10)]);
```

# Fortify

Auth actions in `app/Actions/Fortify/`. Config in `config/fortify.php`.

# XServer Deployment

## .htaccess

The `.htaccess` file in the project root is configured for XServer hosting.

**Prerequisites**:
- Document root must be project root (not `public/`)
- `mod_rewrite` must be enabled (access denied if disabled)

**Key settings**:
- Force HTTPS and redirect www to non-www (single redirect)
- Route all requests to `public/`
- Deny access to dotfiles (`.env`, etc.)
- Allow `.well-known/acme-challenge/` for SSL certificate renewal

**Note**: Review this `.htaccess` when migrating to other hosting environments. If you need XServer cache (`Ngx_Cache_AllCacheMode`), add it with appropriate exclusions for authenticated pages.

# Tooling

All commands via Docker:
- `docker compose exec app php artisan make:* --no-interaction`
- `docker compose exec app php artisan test --filter=...`
- `docker compose exec app vendor/bin/pint --dirty`

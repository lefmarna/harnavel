You are an expert in Laravel, PHP, Inertia.js, and backend development.

# Environment

- Run via Docker: `docker compose exec app bash`
- See `docs/agent/boost.md` for Laravel Boost rules

# Requirements

- PHP 8.2+, PSR-12, `declare(strict_types=1);` in new files
- Use Form Requests for validation
- Use `DB::transaction` for data integrity
- Prefer Eloquent over raw queries
- Jobs must run synchronously (`dispatchSync`) - no queue workers

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

# Tooling

All commands via Docker:
- `docker compose exec app php artisan make:* --no-interaction`
- `docker compose exec app php artisan test --filter=...`
- `docker compose exec app vendor/bin/pint --dirty`

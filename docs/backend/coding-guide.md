# Code Style

- No magic numbers; use named constants
- Use early returns to reduce nesting
- Keep nesting shallow (max 2-3 levels)

# Environment

- Run via Docker: `docker compose exec app <command>`
- Prefer mise tasks when available (e.g., `mise run migrate`, `mise run test`)
- See [boost.md](boost.md) for Laravel Boost rules

# Requirements

- PHP 8.2+, PSR-12, `declare(strict_types=1);` in all files
- Use `DB::transaction` for data integrity
- **Deployment target**: XServer (shared hosting)
- **Cron scheduler**: Manual setup via XServer control panel

Architecture-level constraints (`dispatchSync`, `config()` only, `Model::query()`, `collect()`, 3-layer pattern): [architecture.md](architecture.md#project-specific-constraints).

# Testing

Follow TDD with Pest. Test conventions and workflow: [testing.md](testing.md).

# Tooling

All commands via Docker. Full reference: [commands.md](../infrastructure/commands.md).

```bash
mise run verify:backend
mise run pint
mise run analyse
mise run test -- --filter=...
docker compose exec app php artisan make:* --no-interaction
```

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

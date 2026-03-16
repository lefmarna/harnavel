# Project Context

A starter template built with Laravel 12 + Inertia.js v2 (React 19) + Tailwind CSS v4.
Local development uses Docker (nginx + PHP-FPM + MariaDB). Deployment target is XServer shared hosting.

## Tech Stack

- PHP 8.4 / Laravel 12 / Fortify v1 / Socialite v5
- Inertia.js v2 (React 19) / Tailwind CSS v4 / Wayfinder
- Pest v4 / Larastan v3 / Pint v1
- MariaDB / Docker Compose / mise

## Layout / operational files

- `app/Params/`, `app/Repositories/`, `app/Services/`, `app/ViewModels/` — backend layer objects
- `app/Concerns/`, `app/Support/` — shared traits and helpers
- `e2e/` — Playwright E2E tests
- `.docker/` — Docker config (nginx, PHP)
- `docs/` — agent and engineering docs
- `storage/logs/laravel.log` — Laravel log; inspect only recent entries (for example, `tail -n 200 storage/logs/laravel.log`) because it can grow large

## Architecture

- Queue workers and SSR are unavailable due to XServer shared-hosting constraints.

## Verification

- Backend: `mise run verify:backend`
- Frontend: `pnpm format`, `pnpm lint`, `pnpm lint:typecheck`, `pnpm test:e2e --reporter=dot`
- Docs: `pnpm lint:docs` (covers `docs/` only)
- Done when all relevant checks above pass.

## Testing

- Test titles are written in Japanese.
- New pages require at least a smoke E2E test.

## Reference Docs

- Laravel Boost guidelines: `docs/generated/boost.md`
- Commands: `docs/commands.md`
- Infrastructure: `docs/infrastructure.md`
- Planning: `docs/planning.md`
- Spec workflow: `docs/generated/specs.md`

## Documentation

- `README.md` is in Japanese. `docs/` is in English. `docs/plans/` may use either.
- Files under `docs/generated/` are auto-generated; do not edit manually.

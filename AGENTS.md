# Project Context

A starter template built with Laravel 13 + Inertia.js v3 (React 19) + Tailwind CSS v4.
Local development uses Docker (nginx + PHP-FPM + MariaDB). Deployment target is XServer shared hosting.

## Architecture

- Stack: PHP 8.5 / Laravel 13 / Inertia.js v3 (React 19) / Tailwind CSS v4 / Pest v4 / Larastan v3 / Pint v1 / MariaDB / mise
- Queue workers and SSR are unavailable due to XServer shared-hosting constraints. Do not introduce long-running background workers. Jobs must run synchronously with `dispatchSync()` or `QUEUE_CONNECTION=sync`.
- Prefer solutions that work in both Docker local dev and XServer shared hosting.
- `php` and `php artisan` commands MUST run inside the Docker container. Use `docker compose exec app php artisan ...` (or `docker compose exec -T app ...` in non-interactive contexts). Never run `php artisan` directly on the host.

## Layout

- `app/Params/`, `app/Repositories/`, `app/Services/`, `app/ViewModels/` — backend layer objects
- `app/Concerns/`, `app/Support/` — shared traits and helpers
- `e2e/` — Playwright E2E tests
- `.docker/` — Docker config (nginx, PHP)
- `docs/` — agent and engineering docs
- `storage/logs/laravel.log` — inspect only recent entries (`tail -n 200`) because it can grow large

## Verification

- Start with the narrowest relevant check for the changed area, then run broader checks before finishing.
- Backend: `mise run verify:backend`
- Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:e2e --reporter=dot`
- Docs: `pnpm lint:docs` (covers `docs/` and root markdown entrypoints)
- Browser: Use Playwright CLI (`npx @playwright/cli`) for interactive page verification when available. Follow the CLI loop in `docs/commands.md`; snapshots are saved to `.playwright-cli/` (gitignored). Requires npm registry access or a prior global/cached install.
- For bug fixes, prefer adding a test that reproduces the issue.
- Before finishing, run `git diff` to confirm only intended files are modified and review the diff for regressions and unnecessary changes.
- Done when all checks for the changed area(s) pass and the requested behavior is confirmed.

## Testing

- Test titles are written in Japanese.
- For new features, prefer writing tests before implementation.
- New pages require at least a smoke E2E test.

## Documentation

- `README.md` is in Japanese. `docs/` is in English.
- Files under `docs/generated/` are auto-generated; do not edit manually.
- Treat `CONTEXT.md` as the project domain glossary. If `CONTEXT-MAP.md` exists, use it to find the relevant context glossary instead.
- Before planning, specifying, designing, or refactoring an area, read the relevant glossary and ADRs in `docs/adr/` when they exist.
- Use glossary terms for domain concepts and surface ADR conflicts explicitly instead of silently overriding recorded decisions.
- Keep `CONTEXT.md` focused on domain language, not implementation details. Create or update context docs and ADRs lazily as terms or decisions are resolved, preferably through `grill-with-docs`.

## Reference Docs

- Laravel Boost guidelines: `docs/generated/boost.md`
- Commands: `docs/commands.md`
- Infrastructure: `docs/infrastructure.md`
- Spec workflow: `docs/generated/specs.md`

# Project Context

A starter template built with Laravel 12 + Inertia.js v2 (React 19) + Tailwind CSS v4.
Local development uses Docker (nginx + PHP-FPM + MariaDB). Deployment target is XServer shared hosting.

## Architecture

- Stack: PHP 8.4 / Laravel 12 / Inertia.js v2 (React 19) / Tailwind CSS v4 / Pest v4 / Larastan v3 / Pint v1 / MariaDB / mise
- Queue workers and SSR are unavailable due to XServer shared-hosting constraints. Do not introduce long-running background workers.
- Prefer solutions that work in both Docker local dev and XServer shared hosting.

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
- Frontend: `pnpm format:check`, `pnpm lint`, `pnpm lint:typecheck`, `pnpm test:e2e --reporter=dot`
- Docs: `pnpm lint:docs` (covers `docs/` and root markdown entrypoints)
- Browser: Use Playwright CLI (`npx @playwright/cli`) for interactive page verification when available. Requires npm registry access or a prior global/cached install. Snapshots saved to `.playwright-cli/` (gitignored).
- For bug fixes, prefer adding a test that reproduces the issue.
- Before finishing, run `git diff` to confirm only intended files are modified and review the diff for regressions and unnecessary changes.
- Done when all checks for the changed area(s) pass and the requested behavior is confirmed.

## Testing

- Test titles are written in Japanese.
- For new features, prefer writing tests before implementation.
- New pages require at least a smoke E2E test.

## Documentation

- `README.md` is in Japanese. `docs/` is in English. `docs/plans/` may use either.
- Files under `docs/generated/` are auto-generated; do not edit manually.

## Reference Docs

- Laravel Boost guidelines: `docs/generated/boost.md`
- Commands: `docs/commands.md`
- Infrastructure: `docs/infrastructure.md`
- Planning: `docs/planning.md`
- Spec workflow: `docs/generated/specs.md`

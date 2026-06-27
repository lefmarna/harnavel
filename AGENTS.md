# Project Context

A Laravel 13 + Inertia.js v3 (React 19) + Tailwind CSS v4 starter template.
Two environments: Docker locally (nginx + PHP-FPM + MariaDB), XServer shared hosting in production.

## Architecture

- Stack: PHP 8.5 / Laravel 13 / Inertia.js v3 (React 19) / Tailwind CSS v4 / Pest v4 / Larastan v3 / Pint v1 / MariaDB / mise.
- XServer has no queue workers, SSR, or long-running processes. Run jobs synchronously (`dispatchSync()` or `QUEUE_CONNECTION=sync`), and prefer solutions that work in both Docker and XServer.
- Run `php` / `php artisan` only inside the Docker app container (`docker compose exec app ...`, or `docker compose exec -T app ...` non-interactively) — never on the host.
- Node.js, pnpm, and mise run on the host, not in the container.

## Layout

- `app/Params`, `app/Repositories`, `app/Services`, `app/ViewModels` — backend layer objects; `app/Concerns`, `app/Support` — shared traits and helpers.
- `e2e/` Playwright E2E tests; `.docker/` nginx/PHP config; `docs/` engineering docs.
- `storage/logs/laravel.log` can grow large — read recent entries only (`tail -n 200`).

## Verification

- Start with the narrowest check for the changed area, then widen before finishing.
- Backend: `mise run verify:backend`.
- Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:e2e --reporter=dot`.
- Docs: `pnpm lint:docs`.
- Browser checks: follow the Playwright CLI loop in `docs/commands.md`.
- `delivery.yml` deploys to XServer with `rsync --delete` and `migrate --force`, so editing it can delete live server data — review the rsync excludes, `.deployignore`, develop-only `.htaccess`/robots, release-critical checks, and post-deploy commands together.
- Before finishing, run `git diff` to confirm only intended changes. Done when the changed area's checks pass and the requested behavior is confirmed.

## Testing

- Tests are the behavioral spec: author `it('...')->todo()` cases and get them approved before implementing (`docs/testing-as-spec.md`).
- New features go test-first (red → green via the `tdd` skill); bug fixes start with a test that reproduces the issue.
- Test titles are Japanese, in a `describe`/`it` hierarchy. New pages need at least a smoke E2E test.

## Documentation

- `README.md` is Japanese; `docs/` is English. `docs/generated/` is auto-generated — never edit by hand.
- Multiple agents (Claude Code, Codex) work this repo: keep durable knowledge in repo docs, never a single tool's private memory.
- Before planning, designing, or refactoring an area, read its glossary and ADRs. `CONTEXT.md` is the domain glossary (use `CONTEXT-MAP.md` if present); ADRs live in `docs/adr/` (ADR-NNNN) and `docs/adr/template/` (TADR-NNNN) — see `docs/adr/README.md`.
- Use glossary terms and surface ADR conflicts instead of overriding them silently. After completing work, record only durable decisions as ADRs and tidy `CONTEXT.md` (TADR-0001); squash provisional ADRs rather than leaving `Superseded` tombstones (TADR-0005).

## Reference Docs

- Laravel Boost guidelines: `docs/generated/boost.md`
- Testing as specification: `docs/testing-as-spec.md`
- Commands: `docs/commands.md`
- Infrastructure: `docs/infrastructure.md`
- Spec workflow: `docs/generated/specs.md`
- Cross-app convergence: upstream (apps → template) `/absorb-from-apps`, downstream (template → app) `/merge-template`; decision model in TADR-0003 (`docs/adr/template/0003-shared-surface-convergence.md`)

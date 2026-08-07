# Project Context

A Laravel + Inertia (React) starter template. Docker is used locally; production runs on XServer shared hosting.

- Do not preserve backward compatibility. Delete obsolete paths instead of adding compatibility layers, fallbacks, or deprecated aliases.
- Run PHP, Composer, and Artisan inside the Docker `app` container; run Node.js, pnpm, and mise on the host.
- XServer has no queue workers, SSR, or long-running processes. Run jobs synchronously and keep solutions viable on both Docker and XServer.
- Write the test first for any behavioral change, and give every new page a Pest Feature spec plus a Playwright smoke E2E test. Add `mutates()` to new or changed deterministic application-logic classes with meaningful observable branches, not to thin wiring or external-I/O orchestration — the mutation gate only checks classes that opt in.
- Run DB-backed Pest through `mise run test` or `mise run mutate`, and only one Playwright process at a time; concurrent runs are rejected by design.
- Gates: `mise run verify:backend`; `pnpm format:check`, `lint:check`, `types:check`, `test:unit`, `test:e2e`; `pnpm lint:docs`.
- `README.md` is Japanese and `docs/` is English; never hand-edit `docs/generated/` or Laravel Boost-generated skills.
- Tests are the behavioral specification (`docs/testing-as-spec.md`) and Laravel ecosystem guidelines live in `docs/generated/boost.md`. Read `CONTEXT.md` and `docs/adr/` before changing an area; `docs/commands.md` lists commands.

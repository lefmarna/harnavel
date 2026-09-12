# Project rules

A Laravel + Inertia React starter template.

- PHP/Composer/Artisan: Docker `app`. Node/pnpm/mise: host.
- Production: XServer shared hosting; no queue workers, SSR, or long-running processes.
- Behavior changes start with failing tests (`docs/testing-as-spec.md`).
- No tests/checks for config/tooling/CI wiring except TADR-0001.
- Generic improvements belong in the template.
- README and code comments: Japanese; docs: English.
- Do not hand-edit generated docs or Boost skills (Kiro exception: TADR-0008).
- Validation (as relevant): `mise run test`, `mise run mutate --min=100`, `mise run verify:backend:full` (backend + mutation + E2E); `pnpm format:check`, `lint:check`, `types:check`, `test:unit`, `lint:docs`.
- Run local Playwright serially; runs share `app_e2e`.
- Delegate read-heavy exploration to `explorer`.

References: `CONTEXT.md`, `docs/adr/`, `docs/logging-guidelines.md`, `docs/generated/boost.md`.

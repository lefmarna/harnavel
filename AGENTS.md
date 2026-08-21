# Project context

A Laravel + Inertia React starter template.

## Environment constraints

- Local development uses Docker; production runs on XServer shared hosting.
- Run PHP, Composer, and Artisan in the Docker `app` container; run Node.js, pnpm, and mise on the host.
- Production cannot depend on queue workers, SSR, or other long-running processes.

## Project rules

- Tests are the canonical specification of behavior: write a failing test before a behavioral change (`docs/testing-as-spec.md`).
- Configuration, tooling, and CI wiring are not behavior and get no tests or checks of their own (TADR-0001 bounds the exception).
- Generic improvements go to the template, not one app.
- `README.md` is Japanese and `docs/` is English; `docs/generated/` and Boost-managed skills are generated, never hand-edited.
- Validation: `mise run test` and `mise run mutate` (targeted backend), `mise run verify:backend:full` (full backend, mutation, and E2E), `pnpm format:check`, `lint:check`, `types:check`, `test:unit`, and `lint:docs`.
- Run only one local Playwright process at a time because local runs share `app_e2e`.
- Delegate read-heavy exploration to the `explorer` subagent.

## References

- `CONTEXT.md` (domain terms), `docs/adr/` (decisions), and `docs/generated/boost.md` (Laravel guidance).

# Project context

A Laravel + Inertia React starter template.

## Environment constraints

- Local development uses Docker; production runs on XServer shared hosting.
- Run PHP, Composer, and Artisan in the Docker `app` container; run Node.js, pnpm, and mise on the host.
- Production cannot depend on queue workers, SSR, or other long-running processes.

## Project rules

- Write a failing test before implementing a behavioral change. Behavior is what the application does for a user or caller; configuration and tooling are not behavior. Tests are the canonical behavioral specification; see `docs/testing-as-spec.md`.
- Comments explain only non-obvious rationale, constraints, or safety; they do not restate the code. Type annotations and tool-required directives are exempt.
- `README.md` is Japanese and `docs/` is English. Do not hand-edit `docs/generated/` or Boost-managed skills outside their owning refresh workflows.
- Validation entry points: targeted backend `mise run test`; mutation `mise run mutate`; full backend, mutation, and Chromium E2E `mise run verify:backend:full`; frontend and docs `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:unit`, and `pnpm lint:docs`.
- Run only one local Playwright process at a time because local runs share `app_e2e`.

## References

- `CONTEXT.md` defines domain terms and `docs/adr/` records architectural decisions. Consult only entries relevant to the task.
- Laravel-specific guidance is in `docs/generated/boost.md`.

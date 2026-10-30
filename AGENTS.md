# Project rules

A Laravel + Inertia React starter template.

- PHP/Composer/Artisan: Docker `app`. Node/pnpm/mise: host.
- Production: XServer shared hosting; no queue workers, SSR, or long-running processes.
- Behavior changes start with failing tests (`docs/testing-as-spec.md`).
- No tests/checks for config/tooling/CI wiring except TADR-0001.
- Generic improvements belong in the template. Code shared with it keeps its names and structure, so apps stay consistent through it.
- README and code comments: Japanese; docs: English.
- Docs and comments hold only what code, tests, and general knowledge cannot reveal.
- Do not hand-edit generated docs or Boost skills (Kiro exception: TADR-0008).
- Validation (as relevant): `mise run test`, `mise run mutate --min=100`, `mise run verify:backend:full` (backend + mutation + E2E).
- Run local Playwright serially within each worktree; runs share its E2E database.
- Delegate broad searches to a subagent; read files you will edit directly.

References: `GLOSSARY.md`, `docs/adr/`, `docs/branches-and-ci.md`, `docs/logging-guidelines.md`, `docs/generated/boost.md`.

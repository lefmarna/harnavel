# Project rules

A Laravel + Inertia React starter template.

- PHP/Composer/Artisan: Docker `app`. Node/pnpm/mise: host.
- Production: XServer shared hosting; no queue workers, SSR, or long-running processes.
- Behavior changes start with failing tests (`docs/testing-as-spec.md`).
- No tests/checks for config/tooling/CI wiring except TADR-0001.
- Generic improvements belong in the template.
- README: Japanese; docs: English.
- Do not hand-edit generated docs or Boost skills (Kiro exception: TADR-0008).
- Run local Playwright serially; runs share `app_e2e`.
- Delegate read-heavy exploration to `explorer`.
- Keep only a one-line project summary and non-inferable session constraints.

References: `CONTEXT.md`, `docs/adr/`, `docs/logging-guidelines.md`, `docs/generated/boost.md`.

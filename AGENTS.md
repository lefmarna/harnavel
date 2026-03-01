# Agent Operating Guide

Do not start work after reading only `AGENTS.md`.

Before planning, implementation, or review:

1. Read `docs/generated/boost.md` **in full**. This is the primary project ruleset and must not be skipped.
2. Read `docs/guides/common.md`.
3. Read the task-specific guide for the current change:
   - Frontend: `docs/guides/frontend.md`
   - Backend: `docs/guides/backend.md`
   - Infrastructure/setup: `docs/guides/infrastructure.md`
   - Plan files: `docs/guides/planning.md`
   - Specification workflow: `docs/generated/specs.md`, `.kiro/requirements/`, `.kiro/specs/`
4. Read supporting docs only when relevant:
   - Project context: `docs/context/project.md`
   - Deployment constraints: `docs/context/deploy.md`
   - Commands: `docs/references/commands.md`
   - Schema policy: `docs/references/schema-design-spec.md`
   - Review workflow: `docs/review-gate/README.md`

If a task spans multiple areas, read every relevant guide before making changes.

## Verification

- Run the scope-appropriate checks from `docs/references/commands.md`.
- For backend code changes, run `mise run verify:backend` (`Pint -> Larastan -> Pest`).
- For docs-only changes, run `pnpm lint:docs`.
- If `README.md` or `AGENTS.md` changed, also manually verify their links and entry text because `pnpm lint:docs` checks `docs/` only.

## Review Gate

- Follow `docs/review-gate/README.md`.
- For docs-only changes, wait for explicit user confirmation before requesting `codex-review`.

## Documentation Boundaries

- `README.md` is for humans.
- `docs/` is for AI agents and engineering operations.
- `docs/plans/` is plan-mode only. Follow `docs/guides/planning.md`.
- `docs/` uses English by default.
- `README.md` uses Japanese.
- `docs/plans/` may use Japanese or English.
- `docs/review-gate/*_summary.md` may contain Japanese review logs or JSON.
- Never manually edit generated files: `docs/generated/boost.md`, `docs/generated/specs.md`.

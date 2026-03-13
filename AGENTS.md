# Agent Operating Guide

This file is the primary source of repo-specific instructions.
Prefer code and tests over extra rules. Keep docs minimal and repo-specific.

## Repository Intent

- This repository is a reusable Laravel + Inertia + Docker starter.
- Current starter scope: auth, dashboard, profile/settings, two-factor auth, and Google login.
- Current deployment target is XServer shared hosting.
- Replace these starter assumptions when the repository is initialized into a real product.

## Core Principles

- Simplicity first. Prefer the smallest change that solves the problem cleanly.
- Minimal impact. Touch only what is necessary and preserve existing behavior unless the change intentionally alters it.

## Read Order

Before planning, implementation, or review:

1. Read `AGENTS.md`.
2. Read extra references only when relevant:
   - Laravel/package/version specifics: `docs/generated/boost.md`
   - Repository commands and verification: `docs/commands.md`
   - Local runtime, ports, mail, and deployment profile: `docs/infrastructure.md`
   - Plan files under `docs/plans/`: `docs/planning.md`
   - Specification workflow: `docs/generated/specs.md`, `.kiro/requirements/`, `.kiro/specs/`
3. If a task spans multiple areas, read every relevant reference before editing.

## Documentation Policy

- `README.md` is for humans. `docs/` is for agents and engineering operations.
- Never manually edit generated files: `docs/generated/boost.md`, `docs/generated/specs.md`.
- `docs/` uses English by default. `README.md` uses Japanese. `docs/plans/` may use Japanese or English. Review logs may be Japanese or JSON.

## Non-Obvious Project Rules

- Backend flow is fixed: `Controller -> Service -> Repository`.
- Use `Params` objects when they make grouped input contracts explicit across layers, including typing, normalization, or structure that would otherwise stay implicit. Avoid them for obvious one-off pass-through input.
- Use `ViewModels` when they make Inertia response contracts explicit, especially for reused or non-trivial props. Avoid them for obvious one-off single-value responses.
- XServer shared-hosting constraints mean queue workers and SSR are out of scope.
- New pages require at least a smoke E2E test.
- Test titles are written in Japanese.

## Verification

If you changed implementation, run the relevant verification commands before finishing:

- Backend code: `mise run verify:backend`
- Frontend code: `pnpm format`, `pnpm lint`, `pnpm lint:typecheck`, plus relevant `pnpm test:e2e`
- Docs-only: `pnpm lint:docs`
- If a change spans multiple areas, run every relevant verification set.
- If `README.md` or `AGENTS.md` changed, manually verify their links and opening text because `pnpm lint:docs` checks `docs/` only.

## Review Gate

- Run the `codex-review` skill after spec or plan updates, after major steps (`>= 5` files, public API changes, or infra/config changes), and before commit, PR, merge, or release.
- For docs-only changes, wait for explicit user confirmation before requesting `codex-review`.

## Planning

- `docs/plans/` is plan-mode only. Follow `docs/planning.md`.

# Laravel Docker Template Guide Index

Laravel 12 + MariaDB + Docker development environment with Inertia React frontend.

## Start Here

Open the task guide for your scope:

- Frontend work (`.ts`, `.tsx`, `.css`) -> `docs/guides/frontend.md`
- Backend work (`.php`) -> `docs/guides/backend.md`
- Infrastructure/setup work -> `docs/guides/infrastructure.md`
- Project context -> `docs/context/project.md`
- Specification work -> `docs/generated/specs.md` + `.kiro/specs/` + `.kiro/requirements/`

## Source-of-Truth Quick Links

- Boost generated rules: `docs/generated/boost.md` (do not edit manually)
- Command reference: `docs/references/commands.md`
- Schema reference: `docs/references/schema-design-spec.md`
- Review gate: `docs/review-gate/README.md`

## Verification (Required After Every Implementation)

Run checks based on changed scope. If a check fails, fix and re-run until clean.

```bash
# Frontend changes
pnpm fix && pnpm lint:typecheck && pnpm test:e2e --project=chromium

# Backend changes
mise run verify:backend && pnpm test:e2e --project=chromium

# Both frontend and backend
pnpm fix && pnpm lint:typecheck && mise run verify:backend && pnpm test:e2e --project=chromium

# Documentation changes
pnpm lint:docs
```

## Review Gate

Run the `codex-review` skill and iterate review -> fix -> re-review until `ok: true`:

- after spec/plan updates
- after major steps (>= 5 files / public API / infra-config changes)
- before commit, PR, or release

## Writing Rules

- One topic, one source of truth. Do not duplicate full rules across files.
- In non-source files, keep only short summaries and links.
- English in `docs/`. Japanese in top-level `README.md`.
- Exception: files under `docs/plans/` may be written in Japanese or English.
- Exception: `docs/review-gate/*_summary.md` may contain Japanese review JSON/logs.
- `README.md`: for humans (overview, setup).
- `docs/`: for AI agents and engineering operations.
- Progressive disclosure: short entry docs, detailed references behind links.
- Plan mode only: `docs/plans/` with date-based filenames.
- Never manually edit generated files:
  - `docs/generated/boost.md`
  - `docs/generated/specs.md`
- Update links whenever files are moved. Run `pnpm lint:docs` after doc changes.

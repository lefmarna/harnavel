# Laravel Docker Template

Laravel 12 + MariaDB + Docker development environment with React/Inertia frontend.

## Always-on Project Guides

Use the appropriate guide for your implementation context:

- **Frontend work** (React/TypeScript): `docs/guides/frontend.md`
- **Backend work** (Laravel/PHP): `docs/guides/backend.md`
- **Environment setup**: `docs/docker.md`
- **Laravel Boost rules**: `docs/agent/boost.md`
- **Available commands**: `docs/commands.md`

## After Task Completion (Verification)

After completing an implementation task, **always** run verification commands based on the scope of changes (see `docs/commands.md` for details). If any check fails, fix the issue automatically and re-run verification until all checks pass.

- **Frontend changes** (`.ts`, `.tsx`, `.css`, etc.):

```bash
pnpm fix                          # Layout & Lint fix
pnpm lint:typecheck               # TypeScript check
pnpm test:e2e --project=chromium  # E2E tests
```

- **Backend changes** (`.php`):

```bash
mise run test                     # PHP tests
pnpm test:e2e --project=chromium  # E2E tests
```

- **Both frontend and backend changes**:
  
```bash
pnpm fix && pnpm lint:typecheck && mise run test && pnpm test:e2e --project=chromium
```

## Project Context (Optional Reference)

For deeper project background when needed:

- `.kiro/steering/product.md` - Product vision and scope
- `.kiro/steering/tech.md` - Technical stack details
- `.kiro/steering/structure.md` - Repository structure details

## Specification Phase Only

Spec-driven development instructions are loaded only during specification work. Use `.kiro/requirements/` for cc-sdd (requirements definition) and `.kiro/specs/` for design/implementation specs. See `docs/specs.md`.

For day-to-day development, follow the always-on guides above; apply cc-sdd only during the specification phase.

# Writing Rules

- Default to English in docs.
- **README.md**: For humans (project overview, setup instructions).
- **docs/**: For AI agents (guidelines, command references).
- Apply progressive disclosure: keep entry points brief, details in sub-docs.
- Planning and design documents must be saved under `docs/plans/` with date-based filenames (e.g., `2025-05-20-task-name.md`).

# Review gate (codex-review)

At key milestones—after updating specs/plans, after major implementation steps (≥5 files / public API / infra-config), and before commit/PR/release—run the codex-review SKILL and iterate review→fix→re-review until clean.

# Laravel Docker Template Guide Index

Laravel 12 + MariaDB + Docker development environment with React/Inertia frontend.

## Navigate by Task

**What are you working on?**

- **Frontend** (`.ts`, `.tsx`, `.css`) → Read [docs/frontend/](docs/frontend/README.md)
- **Backend** (`.php`) → Read [docs/backend/](docs/backend/README.md)
- **Infrastructure** (Docker, setup) → Read [docs/infrastructure/](docs/infrastructure/README.md)
- **Understanding the project** → Read [docs/project/](docs/project/README.md)
- **Specification work** → Read [docs/project/specs.md](docs/project/specs.md), use `.kiro/specs/` and `.kiro/requirements/`

**Reference lookup:**

- [docs/infrastructure/commands.md](docs/infrastructure/commands.md) - All available CLI commands
- [docs/backend/coding-guide.md](docs/backend/coding-guide.md) - Project-specific rules
- [docs/backend/schema-design-spec.md](docs/backend/schema-design-spec.md) - ER diagrams, table definitions
- [docs/backend/boost.md](docs/backend/boost.md) - Laravel Boost MCP rules
- [docs/review-gate/](docs/review-gate/README.md) - Review criteria and checklists

## Verification (Required After Every Implementation)

Run checks based on what you changed (see [docs/infrastructure/commands.md](docs/infrastructure/commands.md) for details). **If any check fails, fix the issue and re-run until clean.**

```bash
# Frontend changes
pnpm fix && pnpm lint:typecheck && pnpm test:e2e --project=chromium

# Backend changes
mise run test && pnpm test:e2e --project=chromium

# Both
pnpm fix && pnpm lint:typecheck && mise run test && pnpm test:e2e --project=chromium

# Documentation changes
# Verify internal links are valid and version/config references match source code
pnpm lint:docs
```

## Review Gate

Run the `codex-review` SKILL and iterate review → fix → re-review until `ok: true` at these milestones:

- After updating specs or plans
- After major steps (>= 5 files / public API / infra-config changes)
- Before commit, PR, or release

## Writing Rules

- English in `docs/`. Japanese in `README.md`.
- **`README.md`**: For humans (project overview, setup instructions).
- **`docs/`**: For AI agents (guidelines, command references).
- Progressive disclosure: entry points brief, details in sub-docs.
- **Plan mode only**: `docs/plans/` with date-based filenames.
- **Default mode**: long-lived docs under domain directories (e.g., `docs/backend/`).
- Never create files in `docs/plans/` outside Plan mode.

## Deep Context (Load Only When Needed)

- `.kiro/steering/product.md` - Product vision
- `.kiro/steering/tech.md` - Technical guardrails
- `.kiro/steering/structure.md` - Repository layout conventions

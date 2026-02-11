# Laravel Docker Template

Laravel 12 + MariaDB + Docker development environment with React/Inertia frontend.

## Project Document Guide

Comprehensive documentation is structured by role. Please refer to the relevant section based on your current task.

### Documentation Structure

- **[Project Overview & Requirements](docs/project/README.md)** (`docs/project/README.md`)
    - **Target**: PM, Requirements Definition
    - **Contents**: Concept, Inception Deck
- **[Backend Development](docs/backend/README.md)** (`docs/backend/README.md`)
    - **Target**: Backend Developers
    - **Contents**: Architecture, DB, API, Security, Performance
- **[Frontend Development](docs/frontend/README.md)** (`docs/frontend/README.md`)
    - **Target**: Frontend Developers
    - **Contents**: UI/UX, Design System, Components, SEO
- **[Infrastructure](docs/infrastructure/README.md)** (`docs/infrastructure/README.md`)
    - **Target**: DevOps, Setup
    - **Contents**: Docker, CI/CD, Testing Strategy, Commands

## After Task Completion (Verification)

After completing an implementation task, **always** run verification commands based on the scope of changes (see `docs/infrastructure/commands.md` for details). If any check fails, fix the issue automatically and re-run verification until all checks pass.

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

## Review gate (codex-review)

At key milestones—after updating specs/plans, after major implementation steps (≥5 files / public API / infra-config), and before commit/PR/release—run the codex-review SKILL and iterate review→fix→re-review until clean.

## Writing Rules

- Default to English in docs.
- **README.md**: For humans (project overview, setup instructions).
- **docs/**: For AI agents (guidelines, command references).
- Apply progressive disclosure: keep entry points brief, details in sub-docs.
- Planning and design documents must be saved under `docs/plans/` with date-based filenames (e.g., `2025-05-20-task-name.md`).

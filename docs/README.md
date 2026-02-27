# Documentation Index

This `docs/` tree is the working documentation for AI agents and maintainers.
It uses progressive disclosure: start from task guides, then open references and context only when needed.

## Start by Task

| Task | First document |
| --- | --- |
| Frontend implementation (`.ts`, `.tsx`, `.css`) | `docs/guides/frontend.md` |
| Backend implementation (`.php`) | `docs/guides/backend.md` |
| Environment setup / Docker / CI | `docs/guides/infrastructure.md` |
| Project context and constraints | `docs/context/project.md` |
| Schema design details | `docs/references/schema-design-spec.md` |
| Specification work | `docs/generated/specs.md` |
| Review gate operation and logs | `docs/review-gate/README.md` |

## Source of Truth Map

| Topic | Source of truth | Notes |
| --- | --- | --- |
| Boost agent rules | `docs/generated/boost.md` | Generated file. Do not edit manually. |
| Specification workflow (Kiro) | `docs/generated/specs.md` | Generated file. Keep filename and content unchanged. |
| Day-to-day coding rules | `docs/guides/common.md` + task guide | Project-specific rules not covered by Boost. |
| Frontend conventions | `docs/guides/frontend.md` | React/Inertia/Tailwind conventions. |
| Backend conventions | `docs/guides/backend.md` | Service/Repository architecture and DB rules. |
| Infrastructure operations | `docs/guides/infrastructure.md` | Setup, runtime, troubleshooting. |
| Commands | `docs/references/commands.md` | Canonical command list. |
| Architecture rationale | `docs/context/architecture.md` | Why the structure exists. |
| Deployment constraints | `docs/context/deploy.md` | Hosting profile and runtime limits. |
| Project-level goals and boundaries | `docs/context/project.md` | Template context. |
| Schema reference | `docs/references/schema-design-spec.md` | ER tables and migration checklist. |
| Review gate logs | `docs/review-gate/` | Keep historical reports. |

## Editing Rules

1. One topic, one source of truth. Do not duplicate full rules across files.
2. In non-source files, keep only short summaries and links.
3. Keep `docs/` in English. Keep top-level `README.md` in Japanese.
4. Exception: files in `docs/plans/` may be Japanese or English.
5. Exception: files in `docs/review-gate/*_summary.md` may include Japanese review JSON/logs.
6. Never manually edit generated files:
- `docs/generated/boost.md`
- `docs/generated/specs.md`
7. Update links whenever files are moved. Run `pnpm lint:docs` after doc changes.

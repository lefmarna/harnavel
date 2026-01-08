# Agent Overview

## Read Order

1. `docs/docker.md` (environment setup)
2. `docs/roles/backend.md` or `docs/roles/frontend.md`
3. `docs/agent/boost.md` (hard rules, tools, tests)
4. `docs/specs.md` (spec-driven development only when needed)

## Scope

- This project is a **Laravel Docker template** for rapid application development.
- Laravel application is deployed at the project root.
- Future stack: Laravel 12 + Inertia (React) + Vite + Tailwind CSS v4.
- `AGENTS.md` is the entry point and table of contents; keep it brief.
- Detailed rules live under `docs/`.

## Development Environment

- **Run Laravel/PHP commands via Docker**: `docker compose exec app <command>`
- **Prefer mise tasks** when available (e.g., `mise run migrate`, `mise run test`).

## Constraints

- Assume queue workers are unavailable in the deployment environment.
- If you introduce Jobs, provide a synchronous fallback or avoid worker dependency.

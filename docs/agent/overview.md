# Agent Overview

## Read Order

1. `docs/docker.md` (environment setup)
2. `docs/roles/backend.md` or `docs/roles/frontend.md`
3. `docs/agent/boost.md` (Laravel Boost hard rules, tools, tests)
4. `docs/specs.md` (spec-driven development only when needed)

## Scope

- This is a **Laravel 12 + Inertia (React) + Tailwind CSS v4** application.
- Infrastructure is Docker-based (Nginx, MariaDB, Mailpit).
- `AGENTS.md` is the entry point and table of contents; keep it brief.
- Detailed rules live under `docs/`.

## Development Environment

- **Run commands via Docker**: `docker compose exec app bash`
- **Prefer mise tasks** when available (e.g., `mise run migrate`, `mise run test`)
- **Use pnpm** for all frontend tasks (not npm)

## Constraints

- **Deployment target**: XServer (shared hosting).
- **No background async processes** (no queue workers, no SSR server).
- Jobs must run synchronously (`dispatchSync` or `QUEUE_CONNECTION=sync`).
- **Cron scheduler requires manual setup** via XServer control panel.

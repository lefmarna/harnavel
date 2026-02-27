# Infrastructure Guide

## Scope

Use this file for local environment setup, runtime operations, and CI-oriented troubleshooting.
For canonical command syntax, use `docs/references/commands.md`.

## Setup

Prerequisites and setup steps are in the top-level `README.md`.

## Local Runtime Topology

| Service | Role | Default Port |
| --- | --- | --- |
| `nginx` | Web server (reverse proxy to PHP) | `8080` |
| `app` | PHP-FPM with Laravel | - |
| `mariadb` | Main database | `3306` |
| `mariadb-testing` | Testing database (isolated) | - |
| `mailpit` | Mail catcher (SMTP + Web UI) | `1025`, `8025` |

Default local endpoints:
- App: `http://localhost:8080`
- Mailpit: `http://localhost:8025`

## Environment Variables

| Variable | Meaning | Default |
| --- | --- | --- |
| `APP_NAME` | Container name prefix | `app` |
| `NGINX_PORT` | Nginx exposed port | `8080` |
| `MARIADB_PORT` | MariaDB exposed port | `3306` |
| `MAILPIT_SMTP_PORT` | Mailpit SMTP port | `1025` |
| `MAILPIT_UI_PORT` | Mailpit web UI port | `8025` |

## Operations

Typical daily operations:
- Start/stop containers via `mise run up|stop|down`
- Access app shell via `mise run app`
- Run migrations/tests via `mise` tasks

Use `docs/references/commands.md` as the source of truth for exact commands.

## CI/CD Context

- CI runs lint + tests before merge.
- Playwright on CI runs Chromium by default for speed.
- Deployment profile and hosting constraints are documented in `docs/context/deploy.md`.
- Workflow-level deployment details (build/package/release) are in `docs/context/deploy.md` and command-level details are in `docs/references/commands.md`.

## Troubleshooting

Port conflicts:
- adjust `NGINX_PORT` and `MARIADB_PORT` in `.env`

Broken container state:

```bash
mise run destroy
mise run build
mise run up
```

Database reset:

```bash
mise run fresh
```

Container shell and logs:

```bash
mise run app
mise run logs
```

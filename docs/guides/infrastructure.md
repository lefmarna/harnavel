# Infrastructure Guide

## Scope

Use this file for local runtime facts that are specific to this project. For exact commands, use `docs/references/commands.md`.

## Setup

Human-facing setup steps live in `README.md`.

## Local Runtime Topology

| Service | Role | Default Port |
| --- | --- | --- |
| `nginx` | Web server (reverse proxy to PHP) | `8080` |
| `app` | PHP-FPM with Laravel | - |
| `mariadb` | Main database | `3306` |
| `mariadb-testing` | Testing database (isolated) | - |
| `mailpit` | Mail catcher (SMTP + Web UI) | `1028`, `8028` |

Default local endpoints:
- App: `http://localhost:8080`
- Mailpit: `http://localhost:8028`

## Environment Variables

| Variable | Meaning | Default |
| --- | --- | --- |
| `APP_NAME` | Container name prefix | `app` |
| `NGINX_PORT` | Nginx exposed port | `8080` |
| `MARIADB_PORT` | MariaDB exposed port | `3306` |
| `MAILPIT_SMTP_PORT` | Mailpit SMTP port | `1028` |
| `MAILPIT_UI_PORT` | Mailpit web UI port | `8028` |

Keep this file and `.env.example` aligned when project-specific ports change.

## Deployment Reference

- Hosting constraints live in `docs/context/deploy.md`.
- Build, packaging, and local commands live in `docs/references/commands.md`.

## Troubleshooting

- Port conflicts: update `NGINX_PORT` and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

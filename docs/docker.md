# Docker Development Environment

## Architecture

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    Nginx    │───▶│  PHP (app)  │───▶│   MariaDB   │
│   :8080     │    │   /app      │    │   :3306     │
└─────────────┘    └─────────────┘    └─────────────┘
                                              │
┌─────────────┐                       ┌───────▼─────┐
│   Mailpit   │                       │   MariaDB   │
│  :1025/8025 │                       │   Testing   │
└─────────────┘                       └─────────────┘
```

## Services

| Service           | Description                       | Port       |
| ----------------- | --------------------------------- | ---------- |
| `nginx`           | Web server (reverse proxy to PHP) | 8080       |
| `app`             | PHP-FPM with Laravel              | -          |
| `mariadb`         | Main database                     | 3306       |
| `mariadb-testing` | Testing database (isolated)       | -          |
| `mailpit`         | Mail catcher (SMTP + Web UI)      | 1025, 8025 |

## Commands

See `docs/commands.md` for the full command reference.

**Direct access (when needed):**

```bash
# App container shell
docker compose exec app bash

# Database
docker compose exec mariadb mysql -u root -p

# Logs
docker compose exec app tail -f storage/logs/laravel.log

# Mailpit UI: http://localhost:8025
```

## Environment Variables

| Variable            | Description           | Default |
| ------------------- | --------------------- | ------- |
| `APP_NAME`          | Container name prefix | app     |
| `NGINX_PORT`        | Nginx exposed port    | 8080    |
| `MARIADB_PORT`      | MariaDB exposed port  | 3306    |
| `MAILPIT_SMTP_PORT` | Mailpit SMTP port     | 1025    |
| `MAILPIT_UI_PORT`   | Mailpit web UI port   | 8025    |

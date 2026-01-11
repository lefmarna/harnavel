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

> **Note:** Prefer `mise run <task>` when available (e.g., `mise run test`, `mise run migrate`). See `mise.toml` for available tasks.

**Local (pnpm):** Run on host machine (Node.js installed locally).

```bash
pnpm run dev              # Vite dev server
pnpm lint                 # ESLint
pnpm format               # Prettier
```

**Container (PHP):** `docker compose exec app bash`

```bash
php artisan test          # Run tests
vendor/bin/pint --dirty   # PHP formatter
composer run dev          # With pail, vite
```

**Database:**

```bash
docker compose exec mariadb mysql -u root -p
```

**Logs:**

```bash
docker compose logs -f app
docker compose exec app tail -f storage/logs/laravel.log

# Mailpit UI
# macOS: open / Linux: xdg-open / WSL: explorer.exe
open http://localhost:8025
```

## Environment Variables

| Variable            | Description           | Default |
| ------------------- | --------------------- | ------- |
| `APP_NAME`          | Container name prefix | app     |
| `NGINX_PORT`        | Nginx exposed port    | 8080    |
| `MARIADB_PORT`      | MariaDB exposed port  | 3306    |
| `MAILPIT_SMTP_PORT` | Mailpit SMTP port     | 1025    |
| `MAILPIT_UI_PORT`   | Mailpit web UI port   | 8025    |

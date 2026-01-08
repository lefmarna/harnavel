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
| `mariadb-testing` | Testing database                  | -          |
| `mailpit`         | Mail catcher (SMTP + UI)          | 1025, 8025 |

## Quick Start

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Start containers
docker compose up -d

# 3. Enter app container
docker compose exec app bash

# 4. Set up Laravel (if starting fresh)
composer install
php artisan key:generate
php artisan migrate
```

## Common Commands

### Development

```bash
# Run artisan commands
docker compose exec app php artisan <command>

# Run composer
docker compose exec app composer <command>

# Run npm/pnpm
docker compose exec app pnpm <command>

# Run tests
docker compose exec app php artisan test

# Run Pint (code formatter)
docker compose exec app vendor/bin/pint --dirty
```

### Database

```bash
# Access MariaDB CLI
docker compose exec mariadb mysql -u root -p

# Run migrations
docker compose exec app php artisan migrate
```

### Logs & Debugging

```bash
# View container logs
docker compose logs -f app

# View Laravel logs
docker compose exec app tail -f storage/logs/laravel.log

# Access Mailpit UI
open http://localhost:8025
```

## Environment Variables

Key variables in `.env`:

| Variable            | Description           | Default |
| ------------------- | --------------------- | ------- |
| `APP_NAME`          | Container name prefix | app     |
| `NGINX_PORT`        | Nginx exposed port    | 8080    |
| `MARIADB_PORT`      | MariaDB exposed port  | 3306    |
| `MAILPIT_SMTP_PORT` | Mailpit SMTP port     | 1025    |
| `MAILPIT_UI_PORT`   | Mailpit web UI port   | 8025    |

## Directory Structure

```
/
├── .docker/          # Docker configuration files
│   ├── nginx/        # Nginx config & Dockerfile
│   └── php/          # PHP config & Dockerfile
├── app/              # Laravel application code
├── config/           # Laravel configuration
├── database/         # Migrations, seeders, factories
├── public/           # Public assets (Nginx serves this)
├── resources/        # Views, JS, CSS
├── routes/           # Route definitions
├── storage/          # Logs, cache, uploads
├── tests/            # Test files
├── compose.yaml      # Docker Compose configuration
└── .env.example      # Environment template
```

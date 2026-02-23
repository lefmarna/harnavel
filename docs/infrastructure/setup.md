# Development Environment Setup

## Prerequisites

| Tool                                      | Purpose                                              | Install                                                |
| ----------------------------------------- | ---------------------------------------------------- | ------------------------------------------------------ |
| [mise](https://mise.jdx.dev/)             | Task runner + manages Node.js 24.13.0 + pnpm 10.28.1 | [mise docs](https://mise.jdx.dev/getting-started.html) |
| [Docker Desktop](https://www.docker.com/) | Backend container runtime                            | docker.com                                             |

## Quick Setup

```bash
cp .env.example .env
mise run setup    # Installs everything, builds containers, runs migrations + seed
pnpm dev          # Start frontend dev server
```

## Manual Setup

Use if you need fine-grained control over each step:

```bash
# 1. Environment variables
cp .env.example .env

# 2. Frontend dependencies
pnpm install

# 3. Backend containers
docker compose up -d

# 4. Backend dependencies
docker compose exec app composer install

# 5. Database
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed

# 6. Frontend dev server
pnpm dev
```

## Access Points

| Service | URL                   |
| ------- | --------------------- |
| App     | http://localhost:8080 |
| Mailpit | http://localhost:8025 |

Docker service details: [docker.md](docker.md). Full command reference: [commands.md](commands.md).

## Troubleshooting

**Port conflicts** - Modify `.env`:

```
NGINX_PORT=9080
MARIADB_PORT=5306
```

**Container issues** - Full rebuild:

```bash
mise run destroy && mise run build && mise run up
```

**Database reset**:

```bash
mise run fresh
```

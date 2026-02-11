# Development Setup

This document defines the standard procedure for setting up the development environment.
It ensures a consistent and reproducible development workflow.

## 1. Prerequisites

- **Docker Desktop**: Running.
- **Node.js**: Latest LTS (v20+).
- **Composer**: v2.x.
- **PNPM**: v9+.

## 2. Setup Commands (Step-by-Step)

1.  **Clone Repository**:
    ```bash
    git clone <repo-url>
    cd <repo-dir>
    ```

2.  **Environment Configuration**:
    ```bash
    cp .env.example .env
    # Edit .env if needed (APP_NAME, DB_PASSWORD)
    ```

3.  **Start Docker Containers**:
    ```bash
    docker compose up -d
    ```

4.  **Install Dependencies**:
    ```bash
    docker compose exec app composer install
    pnpm install
    ```

5.  **Initialize Database**:
    ```bash
    docker compose exec app php artisan key:generate
    docker compose exec app php artisan migrate:fresh --seed
    ```

6.  **Start Frontend Dev Server**:
    ```bash
    pnpm dev
    ```

## 3. Troubleshooting

- **Container Conflicts**: Run `docker compose down --volumes` and retry `up`.
- **Permission Issues**: Check `storage/` and `bootstrap/cache` permissions (`chmod -R 777`).
- **NPM Modules**: Delete `node_modules` and run `pnpm install` again.
- **Composer Autoload**: Run `composer dump-autoload` if classes are not found.

## Verification Checklist

- [ ] **Containers**: Are `app`, `nginx`, `mariadb` running (`docker compose ps`)?
- [ ] **Database**: Can you access the database (`docker compose exec mariadb mysql -u root -p`)?
- [ ] **Frontend**: Is `localhost:5173` (Vite) accessible?
- [ ] **Backend**: Is `localhost` accessible (Laravel)?
- [ ] **Tests**: Do `php artisan test` and `pnpm test` pass?
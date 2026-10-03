#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_CACHE_DIR="$HOME/.cache/composer"

curl -fsSL https://mise.run | sh
export PATH="$HOME/.local/bin:$HOME/.local/share/mise/shims:$PATH"
MISE_YES=1 MISE_TRUSTED_CONFIG_PATHS="$PWD" mise install

pnpm install --frozen-lockfile --store-dir "$HOME/.pnpm-store"
cp .env.example .env
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate

step_env() { php scripts/ci/workflow-env.php .github/workflows/reusable-quality.yml "$1"; }

scripts/ci/wait-for-database.sh
env=$(step_env "Build Assets")
(eval "$env" && pnpm run build)
env=$(step_env "Create E2E Database")
(eval "$env" && php artisan tinker --execute='DB::statement("CREATE DATABASE IF NOT EXISTS app_e2e");')

pnpm exec playwright install --with-deps chromium
env=$(step_env "Run E2E Tests")
(eval "$env" && pnpm test:e2e)

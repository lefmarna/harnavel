#!/usr/bin/env bash
# reusable-test.yml の E2E と同じ手順を、CircleCI の cimg/php とサイドカーの MariaDB で実行する。
set -euo pipefail

export COMPOSER_CACHE_DIR="$HOME/.cache/composer"

curl -fsSL https://mise.run | sh
export PATH="$HOME/.local/bin:$HOME/.local/share/mise/shims:$PATH"
MISE_YES=1 MISE_TRUSTED_CONFIG_PATHS="$PWD" mise install

pnpm install --frozen-lockfile --store-dir "$HOME/.pnpm-store"
cp .env.example .env
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate

scripts/ci/wait-for-database.sh
DB_HOST=127.0.0.1 DB_DATABASE=app_testing pnpm run build
DB_HOST=127.0.0.1 DB_DATABASE=app_testing \
  php artisan tinker --execute='DB::statement("CREATE DATABASE IF NOT EXISTS app_e2e");'

pnpm exec playwright install --with-deps chromium
DB_HOST=127.0.0.1 DB_DATABASE=app_e2e APP_URL=http://localhost:8000 MAIL_MAILER=array pnpm test:e2e

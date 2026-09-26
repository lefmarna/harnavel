#!/bin/sh
set -eu

: "${E2E_DB_DATABASE:?Worktree の E2E DB 設定がありません}"
: "${E2E_DB_USERNAME:?Worktree の E2E ユーザー設定がありません}"
: "${E2E_DB_PASSWORD:?Worktree の E2E パスワード設定がありません}"

export DB_HOST=mariadb DB_PORT=3306 DB_DATABASE="$E2E_DB_DATABASE"
export DB_USERNAME="$E2E_DB_USERNAME" DB_PASSWORD="$E2E_DB_PASSWORD" DB_URL=''
export APP_CONFIG_CACHE=/tmp/laravel-e2e-config.php BOOST_BROWSER_LOGS_WATCHER=false

exec php artisan "$@"

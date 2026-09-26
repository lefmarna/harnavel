#!/bin/sh
set -u

exec 9>"${TMPDIR:-/tmp}/laravel-app-pest.lock"

if ! flock -n 9; then
  echo 'Another DB-backed Pest process is already running. Wait for it to finish, then retry.' >&2
  exit 75
fi

# CI は phpunit.xml、ローカルは共有 DB 内のこの Worktree 専用ユーザーを使う。
if [ -n "${TEST_DB_DATABASE:-}" ]; then
  export DB_HOST=mariadb DB_PORT=3306 DB_DATABASE="$TEST_DB_DATABASE"
  export DB_USERNAME="$TEST_DB_USERNAME" DB_PASSWORD="$TEST_DB_PASSWORD" DB_URL=''
  export APP_CONFIG_CACHE=/tmp/laravel-pest-config.php
fi

exec "$@"

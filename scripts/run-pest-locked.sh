#!/bin/sh
set -u

exec 9>"${TMPDIR:-/tmp}/laravel-app-pest.lock"

if ! flock -n 9; then
  echo 'Another DB-backed Pest process is already running. Wait for it to finish, then retry.' >&2
  exit 75
fi

exec "$@"

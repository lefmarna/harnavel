#!/usr/bin/env bash
set -euo pipefail

for _ in $(seq 60); do
  if php -r 'new PDO("mysql:host=127.0.0.1;port=3306", "root", "password");' 2>/dev/null; then
    exit 0
  fi
  sleep 1
done

echo "MariaDB に接続できません。" >&2
exit 1

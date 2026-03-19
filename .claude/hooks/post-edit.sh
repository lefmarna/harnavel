#!/usr/bin/env bash
set -euo pipefail

input="$(cat)"
file="$(echo "$input" | jq -r '.tool_input.file_path // .tool_input.path // empty')"

[ -z "$file" ] && exit 0

case "$file" in
  # TypeScript / JavaScript: 編集ファイルのみに ESLint + Prettier を実行
  *.ts|*.tsx|*.js|*.jsx|*.mjs)
    [ ! -f "./node_modules/.bin/eslint" ] && exit 0

    ./node_modules/.bin/eslint --fix "$file" >/dev/null 2>&1 || true
    ./node_modules/.bin/prettier --write --log-level warn "$file" >/dev/null 2>&1 || true

    # 残ったエラー（警告除外）をエージェントに返す
    diag="$({ ./node_modules/.bin/eslint --quiet "$file" 2>&1 || true; } | head -20)"
    if [ -n "$diag" ]; then
      jq -Rn --arg msg "$diag" '{
        hookSpecificOutput: {
          hookEventName: "PostToolUse",
          additionalContext: $msg
        }
      }'
    fi
    ;;

  # CSS / HTML / JSON / MD: Prettier のみ実行
  *.css|*.html|*.json|*.md)
    [ ! -f "./node_modules/.bin/prettier" ] && exit 0

    ./node_modules/.bin/prettier --write --log-level warn "$file" >/dev/null 2>&1 || true
    ;;

  # PHP: docker 経由で Pint を実行（コンテナ未起動時はスキップ）
  *.php)
    project_root="$(pwd)"
    rel_file="${file#$project_root/}"
    docker compose exec -T app vendor/bin/pint "$rel_file" --quiet 2>/dev/null || true
    ;;
esac

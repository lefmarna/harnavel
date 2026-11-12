#!/usr/bin/env bash
set -euo pipefail

composer_list() {
  local output status=0
  output=$(composer audit --locked --abandoned=report --format=json --no-interaction -d "$1") || status=$?
  # マルウェアなどフィルターリストへの一致は advisories ではなく filter に出る。
  # 出力が空でも jq は成功するため、-n と input で入力がないことを失敗にする。
  jq -rn --argjson status "$status" '
    def entries: if type == "object" then .[][] else .[] end;
    input | if (.advisories | type) != "object" and (.advisories | type) != "array" then error("composer audit failed") end
    | [(.advisories | entries | ["composer:\(.packageName):\(.advisoryId)", .severity, .title, .link]),
      (.filter // [] | entries | ["composer:\(.packageName):\(.listName):\(.id // .constraint)", .listName, .reason // .listName, .url // ""])]
    | if $status != 0 and length == 0 then error("composer audit failed") end
    | .[] | @tsv' <<< "$output"
}

pnpm_list() {
  local output status=0
  output=$(pnpm audit --json --dir "$1") || status=$?
  jq -rn --argjson status "$status" '
    input | if (.advisories | type) != "object" then error("pnpm audit failed") end
    | [.advisories[]]
    | if $status != 0 and length == 0 then error("pnpm audit failed") end
    | .[] | ["npm:\(.module_name):\(.github_advisory_id)", .severity, .title, .url] | @tsv' <<< "$output"
}

list() {
  local composer pnpm
  # コマンド置換の中では set -e が効かないため、失敗を明示的に返す。
  composer=$(composer_list "$1") || return
  pnpm=$(pnpm_list "$1") || return
  printf '%s\n%s\n' "$composer" "$pnpm" | sed '/^$/d' | sort -u
}

case "${1:-}" in
  list)
    list "${2:-.}"
    ;;

  ratchet)
    : "${2:?基準の ref がありません}"
    baseline=$(mktemp -d)
    git archive "$2" | tar -x -C "$baseline"

    head=$(list .)
    base=$(list "$baseline")
    added=$(comm -23 <(cut -f1 <<< "$head" | sed '/^$/d' | sort -u) <(cut -f1 <<< "$base" | sed '/^$/d' | sort -u))

    if [ -n "$added" ]; then
      echo "Advisories absent from $2:" >&2
      grep -F -f <(printf '%s\n' "$added") <<< "$head" >&2
      exit 1
    fi

    if [ -n "$head" ]; then
      echo "Advisories still present, as on $2:"
      printf '%s\n' "$head"
    fi
    ;;

  *)
    echo "Usage: $0 list [dir] | ratchet <ref>" >&2
    exit 2
    ;;
esac

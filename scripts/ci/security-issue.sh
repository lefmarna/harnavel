#!/usr/bin/env bash
set -euo pipefail

: "${GITHUB_REPOSITORY:?}"
action=${1:?}
base=${2:?}
title="Security fix needs attention on $base"

# 検索は作成直後の Issue を返さないことがあるため、一覧を全ページたどる。PR も一覧に含まれる。
numbers=$(gh api --paginate "repos/$GITHUB_REPOSITORY/issues?state=open&per_page=100" \
  --jq ".[] | select(.pull_request == null and .title == \"$title\") | .number")
number=${numbers%%$'\n'*}

# Organization の所有では担当者にできないため、担当者なしで作る。
create() {
  gh issue create --repo "$GITHUB_REPOSITORY" --assignee "${GITHUB_REPOSITORY_OWNER:?}" \
    --title "$title" --body-file "$1" > /dev/null ||
    gh issue create --repo "$GITHUB_REPOSITORY" --title "$title" --body-file "$1" > /dev/null
}

case "$action" in
  open)
    if [ -n "$number" ]; then
      gh issue edit "$number" --repo "$GITHUB_REPOSITORY" --body-file "${3:?}" > /dev/null
    else
      create "${3:?}"
    fi
    ;;

  comment)
    if [ -n "$number" ]; then
      gh issue comment "$number" --repo "$GITHUB_REPOSITORY" --body-file "${3:?}" > /dev/null
    else
      create "${3:?}"
    fi
    ;;

  close)
    if [ -n "$number" ]; then
      gh issue close "$number" --repo "$GITHUB_REPOSITORY" \
        --comment "No vulnerable dependency or pending merge remains on \`$base\`." > /dev/null
    fi
    ;;

  *)
    echo "Usage: $0 open|comment <base> <file> | close <base>" >&2
    exit 2
    ;;
esac

#!/usr/bin/env bash
# GITHUB_TOKEN による更新ではワークフローが起動しないため、delivery と再走査はここから起動する。
set -euo pipefail

: "${BASE:?反映先のブランチがありません}"
: "${GITHUB_REPOSITORY:?}" "${GITHUB_SHA:?}" "${DEFAULT_BRANCH:?}"

repo=$GITHUB_REPOSITORY
branch=security-fix/$BASE

# 検証後に BASE が進んでいれば、反映後の内容は検証していない組み合わせになる。
rescan() {
  echo "$BASE moved after verification. Rescanning instead of applying $GITHUB_SHA." >> "$GITHUB_STEP_SUMMARY"
  gh workflow run security.yml --ref "$DEFAULT_BRANCH"
}

if [ "$(gh api "repos/$repo/compare/$BASE...$GITHUB_SHA" --jq .behind_by)" != 0 ]; then
  rescan
  exit 0
fi
# 反映後に配備を起動できず再実行しても判定が変わらないよう、反映前の BASE ではなく親と比べる。
read -r verified_tree parent < <(gh api "repos/$repo/git/commits/$GITHUB_SHA" \
  --jq '"\(.tree.sha) \(.parents[0].sha)"')
parent_tree=$(gh api "repos/$repo/git/commits/$parent" --jq .tree.sha)

if ! gh api -X PATCH "repos/$repo/git/refs/heads/$BASE" -f sha="$GITHUB_SHA" -F force=false > /dev/null; then
  if [ "$(gh api "repos/$repo/compare/$BASE...$GITHUB_SHA" --jq .behind_by)" != 0 ]; then
    rescan
    exit 0
  fi
  # workflow_dispatch の検証は PR の必須チェックにならないため、拒まれた反映は人が PR でマージする。
  echo "::error::Could not fast-forward $BASE to $GITHUB_SHA; merge $branch through a pull request." >&2
  exit 1
fi
echo "Applied $GITHUB_SHA to $BASE." >> "$GITHUB_STEP_SUMMARY"

gh api -X DELETE "repos/$repo/git/refs/heads/$branch" > /dev/null || true

if [ "$IS_TEMPLATE" != true ] && [ "$verified_tree" != "$parent_tree" ]; then
  gh workflow run delivery.yml --ref "$BASE"
fi

# main の修正を develop へ取り込む。
if [ "$BASE" = "$DEFAULT_BRANCH" ]; then
  gh workflow run security.yml --ref "$DEFAULT_BRANCH"
fi

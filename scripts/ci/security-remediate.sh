#!/usr/bin/env bash
# develop には先に既定ブランチを取り込み、既定ブランチの修正が次のリリースで戻らないようにする。
set -euo pipefail

: "${GITHUB_REPOSITORY:?}" "${DEFAULT_BRANCH:?}"

if [ $# -eq 0 ]; then
  # ブランチを切り替えると作業ツリーのスクリプトも変わるため、既定ブランチの版を使い続ける。
  tools=$(mktemp -d)
  cp scripts/ci/security-remediate.sh scripts/ci/security-audit.sh scripts/ci/security-issue.sh "$tools/"

  git config user.name 'github-actions[bot]'
  git config user.email '41898282+github-actions[bot]@users.noreply.github.com'

  bases=("$DEFAULT_BRANCH")
  if [ "$DEFAULT_BRANCH" != develop ] && git rev-parse -q --verify refs/remotes/origin/develop > /dev/null; then
    bases+=(develop)
  fi

  failed=0
  for base in "${bases[@]}"; do
    TOOLS=$tools "$tools/security-remediate.sh" "$base" || failed=1
  done
  exit "$failed"
fi

base=$1
branch=security-fix/$base
audit=${TOOLS:?}/security-audit.sh
issue=$TOOLS/security-issue.sh
notes=()

composer_update() {
  composer update "$@" --minimal-changes --no-install --no-scripts --no-interaction
}

# 脆弱な版は Composer が解決から除くため、全体を最小限の変更で解決し直せば直る。
# 直せない脆弱性が残ると全体の解決が失敗するので、パッケージごとに更新する。-W は親が依存する直せない脆弱性も
# 解き直して失敗させるため、範囲は少しずつ広げ、推移的な親には付けない。
fix_composer() {
  local package
  grep -q '^composer:' <<< "$1" || return 0
  composer_update && return 0

  awk -F'[:\t]' '$1 == "composer" { print $2 }' <<< "$1" | sort -u | while read -r package; do
    composer_update "$package" < /dev/null && continue
    # shellcheck disable=SC2046
    composer_update "$package" $(dependents "$package") --with-all-dependencies < /dev/null && continue
    # shellcheck disable=SC2046
    composer_update "$package" $(dependents "$package" --recursive) < /dev/null || true
  done
}

dependents() {
  comm -12 \
    <(composer depends "$@" --locked 2> /dev/null | awk '{ print $1 }' | sort -u) \
    <(jq -r '(.packages + ."packages-dev")[].name' composer.lock | sort -u)
}

# 直せない脆弱性が残ると 1 で終わるが、直せたものは lockfile に反映されている。
# 公開から pnpm の待機期間に満たない修正版は minimumReleaseAgeExclude を書き足さないと lockfile の検証を通らないため、
# 宣言は変えずに次の走査へ回す。
# 直接依存は宣言の範囲も書き換えるため、宣言を戻し、直した版を残したまま lockfile を合わせ直す。
fix_pnpm() {
  grep -q '^npm:' <<< "$1" || return 0
  pnpm audit --fix=update --config.ignore-scripts=true || true
  if ! git diff --quiet -- pnpm-workspace.yaml; then
    git checkout -q -- pnpm-workspace.yaml pnpm-lock.yaml package.json
    notes+=("pnpm also changed pnpm-workspace.yaml, as it does for a fix younger than its release age, so npm fixes wait for the next scan.")
  elif ! git diff --quiet -- package.json; then
    git checkout -q -- package.json
    # 既定では node_modules の状態から最新と判断し、lockfile を合わせ直さない。
    pnpm install --lockfile-only --no-frozen-lockfile --ignore-scripts --config.optimistic-repeat-install=false ||
      git checkout -q -- pnpm-lock.yaml
  fi
}

sync_lockfiles() {
  local changed

  if ! git diff --quiet "origin/$base" -- composer.json; then
    changed=$(jq -rn --slurpfile old <(git show "origin/$base:composer.json") --slurpfile new composer.json '
      [$old[0], $new[0]] | map((.require // {}) + (."require-dev" // {})) as [$o, $n]
      | ($o + $n | keys[]) | select(test("/") and $o[.] != $n[.])')
    if [ -n "$changed" ]; then
      # shellcheck disable=SC2086
      composer_update $changed --with-all-dependencies || return
    else
      composer update --lock --no-install --no-scripts --no-interaction || return
    fi
  fi

  if ! git diff --quiet "origin/$base" -- package.json pnpm-workspace.yaml pnpm-lock.yaml; then
    pnpm install --lockfile-only --no-frozen-lockfile --ignore-scripts || return
  fi
}

back_merge() {
  local conflicts

  if git diff --quiet "HEAD...origin/$DEFAULT_BRANCH"; then
    return 0
  fi

  if ! git merge -q --no-ff --no-edit -m "Merge $DEFAULT_BRANCH into $base" "origin/$DEFAULT_BRANCH"; then
    conflicts=$(git diff --name-only --diff-filter=U)
    if grep -qvxE 'composer\.lock|pnpm-lock\.yaml' <<< "$conflicts"; then
      git merge --abort
      notes+=("Merging \`$DEFAULT_BRANCH\` into \`$base\` conflicts in $(paste -sd ' ' <<< "$conflicts"); merge it by hand.")
      return 0
    fi
    # lockfile だけの競合は base 側を残し、取り込んだ宣言から解決し直す。既定ブランチ側の lockfile の変更が
    # 自動修正だけのときに限る。ほかの変更は base 側を残すと失われ、次のリリースで本番から戻ってしまう。
    # リリースで手で解消したマージも人の変更なので、マージも数える。
    # shellcheck disable=SC2086
    if git log --format=%an "HEAD..origin/$DEFAULT_BRANCH" -- $conflicts | grep -qvxF 'github-actions[bot]'; then
      git merge --abort
      notes+=("\`$DEFAULT_BRANCH\` changes the lockfiles beyond automatic fixes, so merge it into \`$base\` by hand.")
      return 0
    fi
    # shellcheck disable=SC2086
    git checkout -q --ours -- $conflicts
    # shellcheck disable=SC2086
    git add -- $conflicts
    git commit -q --no-edit
  fi

  if ! sync_lockfiles; then
    git reset -q --hard "origin/$base"
    notes+=("The lockfiles could not be reconciled after merging \`$DEFAULT_BRANCH\` into \`$base\`; merge it by hand.")
    return 0
  fi
  git add composer.lock pnpm-lock.yaml
  git diff --cached --quiet || git commit -q --amend --no-edit
}

pending() {
  local ref runs
  git fetch -q origin "+refs/heads/$branch:refs/remotes/origin/$branch" 2> /dev/null || return
  [ "$(git rev-parse "origin/$branch^{tree}")" = "$(git rev-parse 'HEAD^{tree}')" ] || return
  for ref in "origin/$base" "origin/$DEFAULT_BRANCH"; do
    if git merge-base --is-ancestor "$ref" HEAD; then
      git merge-base --is-ancestor "$ref" "origin/$branch" || return
    fi
  done
  # 起動できなかった検証やキャンセルされた検証は、誰も再試行しないため起動し直す。
  runs=$(gh api "repos/$GITHUB_REPOSITORY/actions/workflows/security-fix.yml/runs?branch=$branch&head_sha=$(git rev-parse "origin/$branch")" \
    --jq '[.workflow_runs[] | select(.conclusion != "cancelled")] | length') || return
  [ "$runs" != 0 ]
}

propose() {
  if pending; then
    echo "The same fix for \`$base\` was already proposed." >> "$GITHUB_STEP_SUMMARY"
    return 0
  fi

  if ! git push -q --force origin "HEAD:refs/heads/$branch"; then
    notes+=("Pushing \`$branch\` failed, for example because it changes \`.github/workflows\`; merge it by hand.")
    return 0
  fi
  gh workflow run security-fix.yml --ref "$branch" -f base="$base"
  echo "Proposed a fix for \`$base\` on \`$branch\`." >> "$GITHUB_STEP_SUMMARY"
}

report() {
  local file=$RUNNER_TEMP/security-$base.md

  {
    if [ -n "$1" ]; then
      echo "## Advisories without a fix within the declared constraints on \`$base\`"
      echo
      echo '| Advisory | Severity | Title |'
      echo '| --- | --- | --- |'
      awk -F'\t' '{ printf "| `%s` | %s | [%s](%s) |\n", $1, $2, $3, $4 }' <<< "$1"
      echo
      echo "These need a declaration change. Change a constraint shared with Harnavel there and merge the"
      echo "template; change an app-owned constraint here."
      if [ "$base" = "$DEFAULT_BRANCH" ] && git rev-parse -q --verify refs/remotes/origin/develop > /dev/null; then
        echo "If \`develop\` no longer has them, releasing it fixes \`$base\`."
      fi
      echo
    fi
    if [ ${#notes[@]} -gt 0 ]; then
      echo '## Other problems'
      echo
      printf -- '- %s\n' "${notes[@]}"
    fi
  } > "$file"

  "$issue" open "$base" "$file"
}

git checkout -q -f -B "$branch" "origin/$base"
if [ "$base" != "$DEFAULT_BRANCH" ]; then
  back_merge
fi

findings=$("$audit" list .)
if [ -z "$findings" ] && [ ${#notes[@]} -eq 0 ] && [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$base")" ]; then
  "$issue" close "$base"
  exit 0
fi

fix_composer "$findings"
fix_pnpm "$findings"

# 自動修正は lockfile だけに限る。宣言の変更はテンプレートかアプリで判断する。
unexpected=$(git status --porcelain --untracked-files=no | awk '$2 != "composer.lock" && $2 != "pnpm-lock.yaml" { print $2 }')
if [ -n "$unexpected" ]; then
  git checkout -q -- .
  notes+=("The automatic fix also changed $(paste -sd ' ' <<< "$unexpected"), so it was discarded.")
fi

remaining=$("$audit" list .)
fixed=$(comm -23 <(cut -f1 <<< "$findings" | sort -u) <(cut -f1 <<< "$remaining" | sort -u) | sed '/^$/d')
if [ -n "$fixed" ]; then
  git add composer.lock pnpm-lock.yaml
  git commit -q -F - <<EOF
fix(deps): resolve vulnerable dependencies on $base

$(while read -r key; do echo "- $key"; done <<< "$fixed")
EOF
else
  git checkout -q -- composer.lock pnpm-lock.yaml
fi

if [ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$base")" ]; then
  propose
fi

if [ -n "$remaining" ] || [ ${#notes[@]} -gt 0 ]; then
  report "$remaining"
fi

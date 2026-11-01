#!/usr/bin/env bash
# GitHub Actions の pull_request と同じく、PR の head を現在の base へマージした内容を検証する。
set -euo pipefail

: "${BASE_BRANCH:?PR の base がありません}"

head=$(git rev-parse HEAD)
git fetch -q --no-tags origin "+refs/heads/$BASE_BRANCH:refs/remotes/origin/$BASE_BRANCH"
base=$(git rev-parse "refs/remotes/origin/$BASE_BRANCH")

git checkout -q --detach "$base"
git -c user.name="CI" -c user.email="ci@invalid" merge -q --no-ff --no-edit "$head"

echo "Testing $head merged into $BASE_BRANCH ($base): tree $(git rev-parse 'HEAD^{tree}')"

#!/usr/bin/env bash
# チェックアウトした PR の head を、現在の base へマージする。GitHub Actions の pull_request と同じく、マージ後の内容を検証するため。
set -euo pipefail

: "${BASE_BRANCH:?PR の base がありません}"

head=$(git rev-parse HEAD)
if ! git fetch -q --no-tags origin "+refs/heads/$BASE_BRANCH:refs/remotes/origin/$BASE_BRANCH"; then
  echo "最新の $BASE_BRANCH を取得できないため、クローン時点の $BASE_BRANCH を使います。" >&2
fi
base=$(git rev-parse "refs/remotes/origin/$BASE_BRANCH")

git checkout -q --detach "$base"
git -c user.name="CI" -c user.email="ci@invalid" merge -q --no-ff --no-edit "$head"

echo "Testing $head merged into $BASE_BRANCH ($base): tree $(git rev-parse 'HEAD^{tree}')"

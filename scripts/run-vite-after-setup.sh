#!/bin/sh
set -eu

# Orca の既定タブは setup より先に作られるため、成功した初期化を待つ。
echo 'セットアップ完了を待っています。失敗した場合は Setup タブで修正・再実行してください。'
while [ ! -f storage/framework/.dev-ready ]; do
  sleep 1
done

exec pnpm dev

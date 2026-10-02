#!/usr/bin/env bash
# E2E に成功したツリーを GCS の記録で扱う。delivery は同じツリーの記録があるときだけ、この E2E を配備の根拠にする。
#   check:  このツリーの記録があれば成功する。確認できなければ E2E を実行させるため失敗する。
#   create: E2E に成功したこのツリーを記録する。
set -euo pipefail

: "${E2E_RECORD_BUCKET:?記録先のバケットがありません}"
: "${GCP_WORKLOAD_IDENTITY_AUDIENCE:?Workload Identity のプロバイダがありません}"

tree=$(git rev-parse 'HEAD^{tree}')
name=$(jq -rn --arg name "$REPOSITORY/e2e/$tree.json" '$name | @uri')
token=$(
  curl -fsS https://sts.googleapis.com/v1/token \
    --data-urlencode grant_type=urn:ietf:params:oauth:grant-type:token-exchange \
    --data-urlencode "audience=$GCP_WORKLOAD_IDENTITY_AUDIENCE" \
    --data-urlencode scope=https://www.googleapis.com/auth/cloud-platform \
    --data-urlencode requested_token_type=urn:ietf:params:oauth:token-type:access_token \
    --data-urlencode subject_token_type=urn:ietf:params:oauth:token-type:jwt \
    --data-urlencode "subject_token=$CIRCLE_OIDC_TOKEN_V2" |
    jq -er .access_token
)

case "${1:-}" in
  check)
    record=$(curl -fsS -H "Authorization: Bearer $token" \
      "https://storage.googleapis.com/storage/v1/b/$E2E_RECORD_BUCKET/o/$name?alt=media") || exit 1
    [ "$(jq -r .tree <<< "$record")" = "$tree" ] &&
      [ "$(jq -r .repository <<< "$record")" = "$REPOSITORY" ] || exit 1
    echo "Tree $tree already passed E2E in $(jq -r .build <<< "$record")."
    ;;

  create)
    # 追跡対象のファイルが変わっていれば、記録するツリーと検証した内容が一致しない。
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
      git status --short --untracked-files=no >&2
      echo "E2E 中に追跡対象のファイルが変わったため、ツリーを記録しません。" >&2
      exit 1
    fi

    record=$(jq -cn --arg repository "$REPOSITORY" --arg tree "$tree" --arg build "$CIRCLE_BUILD_URL" \
      '{repository: $repository, tree: $tree, build: $build}')
    # 上書きはしない。同じツリーの記録が既にあれば、それも成功として扱う。
    response=$(mktemp)
    status=$(
      curl -sS -o "$response" -w '%{http_code}' -X POST \
        -H "Authorization: Bearer $token" -H 'Content-Type: application/json' \
        --data-binary "$record" \
        "https://storage.googleapis.com/upload/storage/v1/b/$E2E_RECORD_BUCKET/o?uploadType=media&ifGenerationMatch=0&name=$name"
    )

    case "$status" in
      200) echo "Recorded E2E success for tree $tree." ;;
      412) echo "Tree $tree already has an E2E record." ;;
      *)
        cat "$response" >&2
        echo "E2E は成功しましたが、記録に失敗しました（HTTP ${status}）。delivery はこのツリーの E2E をやり直します。" >&2
        exit 1
        ;;
    esac
    ;;

  *)
    echo "Usage: $0 check|create" >&2
    exit 2
    ;;
esac

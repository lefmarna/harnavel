<!-- app:overview:start -->
# {{PROJECT_NAME}}

{{PROJECT_OVERVIEW}}
<!-- app:overview:end -->

## 技術構成

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 13, PHP 8.5, Fortify     |
| Frontend       | React 19, Inertia v3, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v5, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

## 前提環境

- Orca / Docker Compose / `mise`

<!-- app:development:start -->
{{APP_DEVELOPMENT_NOTES}}
<!-- app:development:end -->

## Orca で開発する

1. 主 checkout を Orca に登録します（共有 DB のデータ保存先のため移動・削除しない）。
2. 作業ごとに worktree を作成します（分岐元は `develop`、ない場合は `main`。詳細は [ブランチ運用](docs/branches-and-ci.md)）。`orca.yaml` が自動で `mise run setup` を実行します（手動実行も可）。
3. 接続先 URL を確認し、フロントエンド変更時のみ Vite を起動します（二重起動は `public/hot` が消えるため避ける）。Cookie はポートで分離されないため、worktree ごとにブラウザプロファイルを分けます（[Worktree development](docs/worktree-development.md)）。

```bash
mise run dev:info
pnpm dev
```

追加 worktree を Orca でアーカイブすると、`mise run worktree:remove` がその DB とコンテナを削除します。残したいデータは先に退避してください。

## 主なコマンド

```bash
mise run up             # コンテナ起動
mise run down           # コンテナ停止 + 削除
mise run dev:info       # この Worktree の URL・DB・Compose 名
mise run infra:stop     # 全 Worktree を down した後で共有 DB・Mailpit を停止
mise run verify:backend # Pint -> Larastan -> Pest
pnpm fix                # Oxlint safe fix + Oxfmt
pnpm types:check        # TypeScript 型チェック
pnpm lint:docs          # リンク検証
pnpm test:e2e           # Playwright E2E
```

## ドキュメント

- [AGENTS.md](AGENTS.md): AI エージェント向けの起点
- [GLOSSARY.md](GLOSSARY.md): ドメイン用語集
- [docs/testing-as-spec.md](docs/testing-as-spec.md): テストの責務分担とミューテーション方針
- [docs/logging-guidelines.md](docs/logging-guidelines.md): ログと通知の運用ルール
- [docs/infrastructure.md](docs/infrastructure.md): XServer の運用前提とサーバー設定
- [docs/worktree-development.md](docs/worktree-development.md): 共有開発環境と worktree の管理
- [docs/branches-and-ci.md](docs/branches-and-ci.md): ブランチ運用・CI・依存関係と脆弱性の更新
- [docs/circleci.md](docs/circleci.md): CircleCI 連携設定
- [docs/adr/README.md](docs/adr/README.md): 設計判断の記録（ADR / Template ADR）
- [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md): 第三者由来のコードと文書のライセンス

<!-- app:notices:start -->
{{APP_NOTICES}}
<!-- app:notices:end -->

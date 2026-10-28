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

- Orca
- Docker / Docker Compose
- `mise`（Node.js と pnpm を揃えるため）

<!-- app:development:start -->
{{APP_DEVELOPMENT_NOTES}}
<!-- app:development:end -->

## Orca で開発する

1. clone したリポジトリを Orca に登録します。主 checkout は共有 DB のデータ保存先でもあるため、追加した worktree の利用中は移動・削除しません。
2. 作業ごとに worktree を作成します。分岐元は、`develop` があるアプリでは `develop`、ない場合は `main` にします（[ブランチ運用](docs/branches-and-ci.md)）。
3. セットアップを有効にしてエージェントを起動します。同梱の `orca.yaml` が `mise run setup` を実行し、完了を待ってからエージェントを起動します。

セットアップ後、作業する worktree のターミナルで URL を確認します。

```bash
mise run dev:info

# フロントエンドを変更するときだけ Vite を起動
pnpm dev
```

アプリの URL は worktree ごとに変わります。Mailpit を含め、接続先は `dev:info` の表示で確認してください。ブラウザの Cookie はポートで分離されないので、worktree ごとにブラウザプロファイルも分けます。

MariaDB と Mailpit は同じアプリの worktree 間で共有し、DB は worktree ごとに分けます。ポートの割り当てや削除の詳細は [Worktree development](docs/worktree-development.md) を参照してください。

Vite は負荷を抑えるため常駐させません。起動していない間は、セットアップ時にビルドしたアセットで表示します。
`pnpm dev` の二重起動は避けてください。ポート衝突で終了した側が `public/hot` を削除するため、先に起動した dev server が生きたままアプリだけビルド済みアセットの配信に切り替わります（E2E はこの状態を検出して中止します）。

作業はそのブランチから PR を作成して取り込みます。不要になった追加 worktree は、Orca のアーカイブ処理で `mise run worktree:remove` を実行してから片付けます。その worktree の DB も削除されるため、残したいデータは先に退避してください。

### 手動でセットアップする場合

Orca のセットアップを省略した場合や、Orca を使わずに開発する場合も、対象の checkout で同じタスクを実行できます。

```bash
mise run setup
mise run dev:info
```

追加 worktree を手動で削除するときは、先にその worktree で `mise run worktree:remove` を実行します。

## 日常的によく使うコマンド

```bash
mise run up             # コンテナ起動
mise run stop           # コンテナ停止
mise run down           # コンテナ停止 + 削除
mise run dev:info       # この Worktree の URL・DB・Compose 名
mise run infra:stop     # 全 Worktree を down した後で共有 DB・Mailpit を停止
mise run app            # app コンテナへ入る
mise run verify:backend # Pint -> Larastan -> Pest（カバレッジ閾値は mise.toml を参照）
pnpm format:check       # Oxfmt チェックのみ
pnpm lint:check         # type-aware Oxlint（変更なし）
pnpm fix                # Oxlint の safe fix + Oxfmt
pnpm types:check        # TypeScript 型チェック
pnpm lint:docs          # docs/ + README/AGENTS/CONTEXT のリンク検証
pnpm test:e2e
```

## ドキュメントの見方

- [README](README.md): 人間向けの概要とセットアップ
- [AGENTS.md](AGENTS.md): AI エージェント向けの起点
- [CONTEXT.md](CONTEXT.md): ドメイン用語集
- [docs/testing-as-spec.md](docs/testing-as-spec.md): テストの責務分担とミューテーション方針
- [docs/logging-guidelines.md](docs/logging-guidelines.md): アプリケーションログの運用ルール
- [TADR-0009](docs/adr/template/0009-frontend-quality-harness.md): フロントエンド品質ゲートの責務分担
- [docs/infrastructure.md](docs/infrastructure.md): XServer の運用前提とサーバー側の設定
- [docs/worktree-development.md](docs/worktree-development.md): 共有開発環境・worktree の作成と削除
- [docs/branches-and-ci.md](docs/branches-and-ci.md): ブランチの役割と、各イベントで実行される CI
- [docs/circleci.md](docs/circleci.md): PR の E2E・ミューテーションを CircleCI で実行する設定
- [docs/adr/README.md](docs/adr/README.md): 設計判断の記録（ADR / Template ADR）
- [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md): 第三者由来のコードと文書のライセンス

プロジェクト固有ルールは `AGENTS.md`、参照情報は `docs/` を確認してください。

<!-- app:notices:start -->
{{APP_NOTICES}}
<!-- app:notices:end -->

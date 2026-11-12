# Harnavel

Harnavel（ハーナベル）は、Orca の worktree で AI エージェントと開発するための Laravel 13 + Inertia React テンプレートです。
作者個人の開発基盤として随時更新しており、互換性や安定性は保証しません。

## 特徴

- **Orca で作業ごとに環境を分離**: MariaDB と Mailpit はアプリ内で共有し、DB とコンテナ（Nginx / PHP）は worktree ごとに分離します（[docs/worktree-development.md](docs/worktree-development.md)）。
- **XServer 共有ホスティング前提**: 常駐プロセスや SSR を使わずに動かし、サーバー上で直接リリースを置き換えます（[docs/infrastructure.md](docs/infrastructure.md)、[TADR-0006](docs/adr/template/0006-in-place-deployment.md)）。
- **テストを仕様として扱う**: 振る舞いの変更は失敗するテストから始め、ミューテーションテストで検証します（[docs/testing-as-spec.md](docs/testing-as-spec.md)）。
- **AI エージェント用のハーネス**: `AGENTS.md`、スキル、ADR を同梱し、派生アプリと汎用改善を双方向に共有します（[TADR-0003](docs/adr/template/0003-template-ownership.md)）。

## 技術構成

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 13, PHP 8.5, Fortify     |
| Frontend       | React 19, Inertia v3, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v5, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

> [!IMPORTANT]
> **OGP 画像はプロジェクトごとに追加してください。** SNS シェア用の既定画像は `public/opengraph-image.jpg`（推奨: 1200x630px）です。リポジトリには含まれないため、デプロイ前に作成してください。

## 前提環境

- Orca / Docker Compose / `mise`

## 新しい派生アプリの初期設定

新しい派生アプリでは、clone した主 checkout でエージェントに [`project-docs-init`](.agents/skills/project-docs-init/SKILL.md) を依頼し、アプリの資料（既定は `old_docs/`）・アプリ名・DB 名・100 ポート単位の `DEV_PORT_BASE` を渡します。派生アプリの README はテンプレート紹介用のこのファイルではなく、ひな形から作られます。初期設定後、主 checkout で環境を用意して検証・コミットします（コミット時のフックは起動中の `app` コンテナを使います）。

```bash
mise run setup
pnpm lint:docs
```

## Orca で開発する

1. 主 checkout を Orca に登録します（共有 DB のデータ保存先のため移動・削除しない）。
2. 作業ごとに worktree を作成します（分岐元は `develop`、ない場合は `main`）。`orca.yaml` が自動で `mise run setup` を実行します（手動実行も可）。
3. 接続先 URL を確認し、フロントエンド変更時のみ Vite を起動します（二重起動は `public/hot` が消えるため避ける）。Cookie はポートで分離されないため、worktree ごとにブラウザプロファイルを分けます。

```bash
mise run dev:info
pnpm dev
```

追加 worktree を Orca でアーカイブすると、`mise run worktree:remove` がその DB とコンテナを削除します。残したいデータは先に退避してください。

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

## ライセンス

独自コードは [MIT License](LICENSE)、第三者由来のコードと文書は [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) に従います。

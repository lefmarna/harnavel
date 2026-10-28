# Harnavel

Harnavel（ハーナベル）は、Orca の worktree で AI エージェントと開発するための Laravel 13 + Inertia React テンプレートです。
開発環境は Docker で動かし、作業ごとにブランチと環境を分けます。
複数案件へ横展開しやすいように、認証・基本画面・開発コマンド・運用ルールを初期状態で揃えています。
作者個人の開発基盤として随時更新しており、互換性や安定性は保証しません。

## 特徴

- **Orca で作業ごとに環境を用意**: worktree の作成時にセットアップし、完了してからエージェントを起動します。MariaDB と Mailpit はアプリ内で共有し、DB は worktree ごとに分けます（[docs/worktree-development.md](docs/worktree-development.md)）。
- **XServer 共有ホスティング前提**: 常駐プロセスや SSR を使わずに動かし（SEO メタタグは `app.blade.php` で初期 HTML に出力します）、サーバー上で直接リリースを置き換えます（[docs/infrastructure.md](docs/infrastructure.md)、[TADR-0006](docs/adr/template/0006-in-place-deployment.md)）。
- **テストを仕様として扱う**: 振る舞いの変更は失敗するテストから始め、ミューテーションテストでテストの強さを確かめます（[docs/testing-as-spec.md](docs/testing-as-spec.md)）。
- **AI エージェント用のハーネス**: `AGENTS.md`、スキル、設計判断の記録（ADR）を同梱し、エージェントが同じ規約で作業できるようにしています（[AGENTS.md](AGENTS.md)、[docs/adr/README.md](docs/adr/README.md)）。
- **派生アプリとの共有**: 派生アプリはこのテンプレートを取り込み続け、汎用的な改善はテンプレートへ戻します（[TADR-0003](docs/adr/template/0003-template-ownership.md)）。

## 技術構成

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 13, PHP 8.5, Fortify     |
| Frontend       | React 19, Inertia v3, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v5, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

> [!IMPORTANT]
> **OGP 画像はプロジェクトごとに追加してください。** SNS シェア用の既定画像は `public/opengraph-image.jpg`（推奨: 1200x630px）で、初期 HTML の OGP/Twitter Card はこの JPG を参照します。リポジトリには含まれていないため、デプロイ前に必ず作成してください。

## 前提環境

- Orca
- Docker / Docker Compose
- `mise`（Node.js と pnpm を揃えるため）

## 新しい派生アプリの初期設定

新しい派生アプリでは、clone した主 checkout でエージェントへ [`project-docs-init`](.agents/skills/project-docs-init/SKILL.md) を明示して依頼します。
アプリの目的・対象ユーザー・機能範囲などの資料（既定は `old_docs/`）と、アプリ名・DB 名・ほかのアプリと重ならないポート枠を渡してください。
このスキルは [派生アプリ用 README のひな形](.agents/skills/project-docs-init/assets/README.md) にアプリ固有の情報を差し込み、ドメイン用語や環境設定の既定値も整えます。通常の worktree 作成時には実行しません。

Harnavel の README はテンプレートの紹介用です。派生アプリの README は専用のひな形を使い、アプリ名・機能・固有の開発設定・注意事項を差し替えます。開発手順などの共通部分は、`merge-template` でひな形の更新に追従します。

アプリ名やポート枠などの既定値を編集した後、主 checkout で環境を用意して検証します。

```bash
mise run setup
pnpm lint:docs
```

セットアップと検証が通ったら、初期設定の変更をコミットしてから以下の開発手順へ進みます。コミット時の検証にも依存パッケージと起動中の `app` コンテナが必要です。
アプリごとのポート枠は `DEV_PORT_BASE` から 100 ポート分を使います。割り当ての詳細は [Worktree development](docs/worktree-development.md) を参照してください。

## Orca で開発する

1. clone したリポジトリを Orca に登録します。主 checkout は共有 DB のデータ保存先でもあるため、追加した worktree の利用中は移動・削除しません。
2. 作業ごとに worktree を作成します。分岐元は通常 `main`、派生アプリに `develop` がある場合は `develop` にします（[ブランチ運用](docs/branches-and-ci.md)）。
3. セットアップを有効にしてエージェントを起動します。同梱の `orca.yaml` が `mise run setup` を実行し、完了を待ってからエージェントを起動します。

セットアップ後、作業する worktree のターミナルで URL を確認します。

```bash
mise run dev:info

# フロントエンドを変更するときだけ Vite を起動
pnpm dev
```

アプリの URL は worktree ごとに変わります。Mailpit を含め、接続先は `dev:info` の表示で確認してください。ブラウザの Cookie はポートで分離されないので、worktree ごとにブラウザプロファイルも分けます。

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

## ドキュメントの見方

- [README](README.md): 人間向けの概要とセットアップ
- [AGENTS.md](AGENTS.md): AI エージェント向けの起点
- [CONTEXT.md](CONTEXT.md): ドメイン用語集（展開先プロジェクトで育てる）
- [docs/testing-as-spec.md](docs/testing-as-spec.md): テストの責務分担とミューテーション方針
- [docs/logging-guidelines.md](docs/logging-guidelines.md): アプリケーションログの運用ルール
- [TADR-0009](docs/adr/template/0009-frontend-quality-harness.md): フロントエンド品質ゲートの責務分担
- [docs/infrastructure.md](docs/infrastructure.md): XServer の運用前提とサーバー側の設定
- [docs/worktree-development.md](docs/worktree-development.md): 共有開発環境・Orca・Worktree の作成と削除
- [docs/branches-and-ci.md](docs/branches-and-ci.md): ブランチの役割と、各イベントで実行される CI
- [docs/circleci.md](docs/circleci.md): PR の E2E・ミューテーションを CircleCI で実行する設定
- [docs/adr/README.md](docs/adr/README.md): 設計判断の記録（ADR / Template ADR）

プロジェクト固有ルールは `AGENTS.md`、参照情報は `docs/` を確認してください。

## ライセンス

このリポジトリ独自のコードは [MIT License](LICENSE) です。第三者由来のコードと文書は、それぞれのライセンスに従います。一覧と許諾文は [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) を参照してください。

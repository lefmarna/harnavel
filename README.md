# Laravel App Template

Laravel 13 + Inertia React + MariaDB を Docker で開発するためのテンプレートです。
複数案件へ横展開しやすいように、認証・基本画面・開発コマンド・運用ルールを初期状態で揃えています。

## 技術構成

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 13, PHP 8.5, Fortify     |
| Frontend       | React 19, Inertia v3, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v5, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

> [!NOTE]
> **SSR は使用しません。** デプロイ先の XServer 共有ホスティングでは Node.js プロセスを常駐できないため、Inertia SSR は無効化しています。SEO メタタグは `app.blade.php` 側で初期 HTML に出力します。

> [!IMPORTANT]
> **OGP 画像はプロジェクトごとに追加してください。** SNS シェア用の既定画像は `public/opengraph-image.jpg`（推奨: 1200x630px）で、初期 HTML の OGP/Twitter Card はこの JPG を参照します。リポジトリには含まれていないため、デプロイ前に必ず作成してください。

## 前提環境

- Docker / Docker Compose
- `mise`（推奨。Node.js と pnpm を揃えるため）

## 最短セットアップ

初回セットアップは `mise` タスクを使うのが最短です。

```bash
# 任意: デフォルト値を調整したい場合だけ先に作成
cp .env.example .env

# ツール導入、依存関係、Git hooks、共有環境とこの Worktree の起動、Laravel初期化、アセットのビルドまで実行
mise run setup

# フロントエンドを変更するときだけ Vite を起動
pnpm dev
```

Vite は負荷を抑えるため常駐させません。起動していない間は `mise run setup` 時にビルドしたアセットで表示するため、フロントエンドの変更を画面で確認するときは `pnpm dev` を起動してください。

セットアップ完了後の既定 URL:

- アプリ: `http://localhost:20000`
- Mailpit: `http://localhost:20004`

各アプリは `DEV_PORT_BASE` で100ポート分を確保し、追加 Worktree に10番ずつ割り当てます。MariaDB と Mailpit は同じアプリの Worktree 間で共有し、開発・テスト・E2E の DB と接続ユーザーは Worktree ごとに分離します。通常は4コンテナ、追加 Worktree は2コンテナだけ起動します。

既存の6コンテナ構成からは、主 Worktree で `mise run dev:migrate` を実行してください。旧 DB を停止してから同じ `storage/mariadb` を引き継ぎ、既存データとアプリケーションキーを保持します。ポートも新しい割り当てに変わります。

Orca は [orca.yaml](orca.yaml) の作成時コマンドでセットアップし、完了を待ってからエージェントを起動します。既存の Repository 設定が local-only の場合は、リポジトリのコマンドを使用する設定に切り替えてください。終了・削除と4アプリへの反映手順は [Worktree development](docs/worktree-development.md) を参照してください。

`pnpm dev` の二重起動は避けてください。ポート衝突で終了した側が `public/hot` を削除するため、先に起動した dev server が生きたままアプリだけビルド済みアセットの配信に切り替わります（E2E はこの状態を検出して中止します）。

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
pnpm test:e2e --project=chromium
```

`pnpm install` は `prepare` スクリプト経由で Git hooks を自動インストールします。ローカル hook は pre-commit で staged files に Pint、Oxlint の safe fix、Oxfmt を順に適用してから、lint/typecheck/unit test/docs と Docker 前提の Larastan を実行します。CI は同じ品質ゲートをリポジトリ全体へ適用します。

## ドキュメントの見方

- [README](README.md): 人間向けの概要とセットアップ
- [AGENTS.md](AGENTS.md): AI エージェント向けの起点
- [CONTEXT.md](CONTEXT.md): ドメイン用語集（展開先プロジェクトで育てる）
- [docs/testing-as-spec.md](docs/testing-as-spec.md): テストの責務分担とミューテーション方針
- [docs/logging-guidelines.md](docs/logging-guidelines.md): アプリケーションログの運用ルール
- [TADR-0009](docs/adr/template/0009-frontend-quality-harness.md): フロントエンド品質ゲートの責務分担
- [docs/infrastructure.md](docs/infrastructure.md): XServerの運用前提（Composer、OPcache）
- [docs/worktree-development.md](docs/worktree-development.md): 共有開発環境・Orca・Worktree の作成と削除
- [docs/adr/README.md](docs/adr/README.md): 設計判断の記録（ADR / Template ADR）

プロジェクト固有ルールは `AGENTS.md`、参照情報は `docs/` を確認してください。

# Laravel App Template

Laravel 12 + Inertia React + MariaDB を Docker で開発するためのテンプレートです。
複数案件へ横展開しやすいように、認証・基本画面・開発コマンド・運用ルールを初期状態で揃えています。

## 技術構成

| Layer          | Technology                       |
| -------------- | -------------------------------- |
| Backend        | Laravel 12, PHP 8.4, Fortify     |
| Frontend       | React 19, Inertia v2, TypeScript |
| Styling        | Tailwind CSS v4, Radix UI        |
| Testing        | Pest v4, Playwright              |
| Infrastructure | Docker (Nginx, MariaDB, Mailpit) |

> [!NOTE]
> **SSR は使用しません。** デプロイ先の XServer 共有ホスティングでは Node.js プロセスを常駐できないため、Inertia SSR は無効化しています。SEO メタタグは `app.blade.php` 側で初期 HTML に出力します。

> [!IMPORTANT]
> **OGP 画像はプロジェクトごとに追加してください。** `public/og-image.png`（推奨: 1200x630px）はリポジトリに含まれていないため、SNS シェアが必要な場合は必ず作成してください。

## 前提環境

- Docker / Docker Compose
- `mise`（推奨。Node.js と pnpm を揃えるため）

## 最短セットアップ

初回セットアップは `mise` タスクを使うのが最短です。

```bash
# 任意: デフォルト値を調整したい場合だけ先に作成
cp .env.example .env

# ツール導入、依存関係、コンテナ構築、起動、Laravel初期化、migrate、seed まで実行
mise run setup

# フロントエンド開発サーバーを起動
pnpm dev
```

セットアップ完了後の既定 URL:

- アプリ: `http://localhost:8080`
- Mailpit: `http://localhost:8025`

## 日常的によく使うコマンド

```bash
mise run up             # コンテナ起動
mise run stop           # コンテナ停止
mise run down           # コンテナ停止 + 削除
mise run app            # app コンテナへ入る
mise run verify:backend # Pint -> Larastan -> Pest
pnpm fix                # ESLint + Prettier 自動修正
pnpm lint:typecheck     # TypeScript 型チェック
pnpm test:e2e --project=chromium
```

## ドキュメントの見方

- [README](README.md): 人間向けの概要とセットアップ
- [AGENTS.md](AGENTS.md): AI エージェント向けの起点
- [docs/references/commands.md](docs/references/commands.md): コマンドのソースオブトゥルース

実装ルール、設計ガイド、レビュー運用は `docs/` 配下を参照してください。

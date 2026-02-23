# Laravel Docker Template

Laravel + MariaDB の Docker 開発環境テンプレートです。

## 構成

| Layer              | Technology                       |
| ------------------ | -------------------------------- |
| **Backend**        | Laravel 12, PHP 8.2+, Fortify    |
| **Frontend**       | React 19, Inertia v2, TypeScript |
| **Styling**        | Tailwind CSS v4, Radix UI        |
| **Testing**        | Pest v4                          |
| **Infrastructure** | Docker (Nginx, MariaDB, Mailpit) |

> [!NOTE]
> **SSRは使用しません。** デプロイ先のXServer共有ホスティングではNode.jsプロセスを常駐できないため、Inertia SSRは無効化しています。SEOメタタグは`app.blade.php`でサーバーサイド設定します。

> [!IMPORTANT]
> **OGP画像を作成してください。** `public/og-image.png`（推奨: 1200x630px）を各プロジェクトで作成してください。SNSシェア時に使用されます。

## 使い方

```bash
# 環境変数をコピー
cp .env.example .env

# コンテナを起動
docker compose up -d

# コンテナに入る
docker compose exec app bash

# セットアップ
composer install
php artisan key:generate
php artisan migrate
pnpm install
pnpm run dev
```

## ドキュメント

詳細は `docs/` を参照してください。

- [Docker 環境](docs/infrastructure/docker.md)
- [バックエンド開発ガイド](docs/backend/README.md)
- [フロントエンド開発ガイド](docs/frontend/README.md)
- [コマンドリファレンス](docs/infrastructure/commands.md)
- [Laravel Boost ルール](docs/backend/boost.md)

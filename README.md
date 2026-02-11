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

- [Docker 環境](docs/01_development_docs/97_docker_architecture.md)
- [バックエンド開発ガイド](docs/01_development_docs/91_backend_implementation_guide.md)
- [フロントエンド開発ガイド](docs/01_development_docs/90_frontend_implementation_guide.md)
- [コマンドリファレンス](docs/01_development_docs/98_commands_reference.md)
- [Laravel Boost ルール](docs/01_development_docs/99_laravel_boost_guidelines.md)

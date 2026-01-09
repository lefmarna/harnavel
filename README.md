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

- [Docker 環境](docs/docker.md)
- [バックエンド開発ガイド](docs/roles/backend.md)
- [フロントエンド開発ガイド](docs/roles/frontend.md)
- [Laravel Boost ルール](docs/agent/boost.md)

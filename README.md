# Laravel Docker Template

Laravel + MariaDB の Docker 開発環境テンプレートです。

## 構成

| Service | Description                     |
| ------- | ------------------------------- |
| Nginx   | Web サーバー (リバースプロキシ) |
| PHP     | Laravel アプリケーション        |
| MariaDB | データベース                    |
| Mailpit | メールキャッチャー              |

## 使い方

```bash
# 環境変数をコピー
cp .env.example .env

# コンテナを起動
docker compose up -d

# コンテナに入る
docker compose exec app bash

# Laravel をセットアップ（新規の場合）
composer install
php artisan key:generate
php artisan migrate
```

## ドキュメント

詳細は `docs/` を参照してください。

- [Docker 環境](docs/docker.md)
- [バックエンド開発ガイド](docs/roles/backend.md)
- [フロントエンド開発ガイド](docs/roles/frontend.md)

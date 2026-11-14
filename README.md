# Harnavel

**日本語** | [English](README.en.md)

Harnavel（ハーナベル）は、Orca の worktree で AI エージェントと開発するための Laravel 13 + Inertia React テンプレートです。
作者個人の開発基盤として随時更新しており、互換性や安定性は保証しません。

**[利用ガイド（Wiki）](https://github.com/lefmarna/harnavel/wiki)** に、手元で動かす手順から派生アプリの作成、XServer への配備までをまとめています。

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

## クイックスタート

前提環境は Docker Compose と `mise` です。worktree の作成には Orca を使います（`git worktree` でも代用できます）。

```bash
git clone https://github.com/lefmarna/harnavel.git
cd harnavel
mise run setup
mise run dev:info
```

表示された URL を開き、確認用ユーザー（`test@example.com` / `Test1234`）でログインできます。派生アプリを作るときは、clone した主 checkout でエージェントに [`project-docs-init`](.agents/skills/project-docs-init/SKILL.md) を依頼します。手順はガイドの「[はじめに](https://github.com/lefmarna/harnavel/wiki/はじめに)」を参照してください。

## 利用ガイド

| ページ                                                                                             | 内容                                             |
| -------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| [はじめに](https://github.com/lefmarna/harnavel/wiki/はじめに)                                     | 手元で動かす、派生アプリを作る                   |
| [開発環境とコマンド](https://github.com/lefmarna/harnavel/wiki/開発環境とコマンド)                 | worktree、ポート、日々使うコマンド               |
| [同梱機能とカスタマイズ](https://github.com/lefmarna/harnavel/wiki/同梱機能とカスタマイズ)         | 認証などの同梱機能と、派生アプリで差し替える場所 |
| [AI エージェントとの開発](https://github.com/lefmarna/harnavel/wiki/AIエージェントとの開発)        | スキル、MCP、検証フック、仕様駆動の進め方        |
| [テストと品質チェック](https://github.com/lefmarna/harnavel/wiki/テストと品質チェック)             | テストの種類と実行方法、CI                       |
| [デプロイと運用](https://github.com/lefmarna/harnavel/wiki/デプロイと運用)                         | XServer への初回配備と日々の運用                 |
| [テンプレート更新の取り込み](https://github.com/lefmarna/harnavel/wiki/テンプレート更新の取り込み) | テンプレートの改善を派生アプリへ取り込む         |
| [トラブルシューティング](https://github.com/lefmarna/harnavel/wiki/トラブルシューティング)         | よくあるエラーと対処                             |

## リポジトリ内のドキュメント

設計判断の理由と運用上の前提を記録した資料です。AI エージェントも参照し、派生アプリに引き継がれます。

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

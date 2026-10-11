# Harnavel

Harnavel（ハーナベル）は、Laravel 13 + Inertia React + MariaDB を Docker で開発するためのテンプレートです。
複数案件へ横展開しやすいように、認証・基本画面・開発コマンド・運用ルールを初期状態で揃えています。
作者個人の開発基盤として随時更新しており、互換性や安定性は保証しません。

## 特徴

- **XServer 共有ホスティング前提**: 常駐プロセスや SSR を使わずに動かし、サーバー上で直接リリースを置き換えます（[docs/infrastructure.md](docs/infrastructure.md)、[TADR-0006](docs/adr/template/0006-in-place-deployment.md)）。
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

MariaDB と Mailpit は同じアプリの Worktree 間で共有し、DB は Worktree ごとに分けます。ポートの割り当て、Orca、削除の手順は [Worktree development](docs/worktree-development.md) を参照してください。

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

`pnpm install` は Git hooks（lefthook）も入れます。pre-commit は staged files を整形してから、lint、型チェック、unit test、docs、Larastan を実行します。

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

このリポジトリ独自のコードは [MIT License](LICENSE) です。次の第三者由来のコードと文書は、それぞれのライセンスに従います。MIT の許諾文は [LICENSE](LICENSE) と同じです。

| 対象                                                                    | 出典                                                                                    | ライセンス | 著作権表示                      |
| ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------- | ---------- | ------------------------------- |
| アプリの骨格                                                            | [laravel/react-starter-kit](https://github.com/laravel/react-starter-kit)               | MIT        | 記載なし                        |
| `resources/js/components/ui/`                                           | [shadcn/ui](https://github.com/shadcn-ui/ui)                                            | MIT        | Copyright (c) 2023 shadcn       |
| `resources/js/hooks/use-clipboard.ts`                                   | [usehooks-ts](https://github.com/juliencrn/usehooks-ts)                                 | MIT        | Copyright (c) 2020 Julien CARON |
| `boost.json` の `skills` に挙げたスキル                                 | [Laravel Boost](https://github.com/laravel/boost)                                       | MIT        | Copyright (c) Taylor Otwell     |
| `kiro-*` スキル、`.kiro/settings/templates/`、`docs/generated/specs.md` | [cc-sdd](https://github.com/gotalab/cc-sdd)                                             | MIT        | Copyright (c) 2025 gotalab      |
| `frontend-design` スキル                                                | [anthropics/skills](https://github.com/anthropics/skills)                               | Apache-2.0 | スキル内の `LICENSE.txt`        |
| `modern-web-guidance` スキル                                            | [GoogleChrome/modern-web-guidance](https://github.com/GoogleChrome/modern-web-guidance) | Apache-2.0 | スキル内の `LICENSE`            |

スキルは `.agents/skills/` と `.claude/skills/` の両方に置いています。`modern-web-guidance` のうち、このリポジトリで修正したファイルは冒頭にその旨を記載しています。上流から更新するときも、修正を引き継いだファイルにはこの記載を残してください。また、上流の説明によると、その文書の一部は [MDN Web Docs](https://developer.mozilla.org/)（Mozilla Contributors）と [W3C](https://www.w3.org/)、[WHATWG](https://whatwg.org)、[IETF](https://www.ietf.org) の仕様に由来します。

---
name: project-docs-init
description: 'テンプレートから展開した新規プロジェクトのdocsを初期化する。old_docs/等の既存コンテキストを読み取り、テンプレートのdocs構成に沿ってプロジェクト固有の情報を反映する。トリガー: 「docsを初期化」「プロジェクトdocs生成」「docs init」「ドキュメント初期化」'
---

# Project Docs Initialization

テンプレートリポジトリから展開した新規プロジェクトのドキュメントを初期化するスキル。

## Purpose

このテンプレートは Laravel + Inertia + Docker のひな形であり、展開先プロジェクトごとに `docs/` の内容をカスタマイズする必要がある。このスキルは既存のプロジェクトコンテキスト（`old_docs/` 等）を読み取り、テンプレートの docs 構成を維持しつつプロジェクト固有の情報を反映する。

重要:
- docs は短く保つ
- 推測できる一般論や長いチュートリアルは追加しない
- プロジェクト固有で、安定していて、コードだけでは分からない情報だけを残す

## Prerequisites

スキル実行前に以下を確認する:

1. プロジェクトのコンテキスト情報が `old_docs/` ディレクトリ（または指定されたディレクトリ）にまとめられていること
2. テンプレートの `docs/` 構造がそのまま残っていること

## Input

ユーザーに以下を確認する:

1. **コンテキストディレクトリ**: プロジェクトの既存ドキュメントがある場所（デフォルト: `old_docs/`）
2. **ポート設定**: このプロジェクトで使う Docker ポート（複数プロジェクト並列開発のため、プロジェクトごとにポートを変える運用）

## Workflow

### Step 1: コンテキスト読み取り

コンテキストディレクトリ内のすべてのファイルを読み取り、以下の情報を抽出する:

- プロジェクト名・概要
- 対象ユーザー・解決する課題
- 主要機能・スコープ
- 技術的な制約や特記事項
- 未決定事項

### Step 2: docs 更新対象の特定

テンプレートの docs 構成のうち、プロジェクト固有の情報で更新が必要なファイル:

| ファイル | 更新内容 |
| --- | --- |
| `AGENTS.md` | タイトルと冒頭のリード文を、展開先プロジェクト前提の文言へ更新 |
| `docs/context/project.md` | プロジェクト概要、スコープをプロジェクト固有の内容で置換 |
| `docs/guides/frontend.md` | Screen Map セクション（ルート一覧とレイアウト/ガード方針）をプロジェクトのページ構成に合わせて更新 |
| `docs/guides/infrastructure.md` | ポート番号を指定値に更新 |
| `.env.example` | ポート番号のデフォルト値を更新 |
| `README.md` | タイトル、冒頭のリード文、概要、セットアップをプロジェクト固有の文言へ更新 |

以下のファイルはテンプレート共通のため**原則変更しない**:

- `docs/guides/backend.md` - アーキテクチャ・コーディングルール
- `docs/guides/frontend.md`（Screen Map 以外） - フロントエンドルール
- `docs/guides/common.md` - 共通の横断ルール
- `docs/guides/planning.md` - `docs/plans/` 用の共通ガイド
- `docs/context/deploy.md` - デプロイ制約
- `docs/references/commands.md` - コマンドのソースオブトゥルース
- `docs/references/schema-design-spec.md` - スキーマポリシー
- `docs/review-gate/README.md` - レビュー運用
- `docs/generated/boost.md` - 自動生成（編集禁止）
- `docs/generated/specs.md` - 自動生成（編集禁止）

補足:
- `README.md` と `AGENTS.md` は構成や役割は維持するが、タイトルと導入文はテンプレート文言のまま残さない
- 「template」「starter」「ひな形」など、展開先で他人事に見える導入文は避ける

### Step 3: 入口ドキュメントの更新

`README.md` と `AGENTS.md` の先頭を以下の方針で更新する:

**`README.md`**

- タイトルをプロジェクト名またはプロジェクト名ベースの文言へ変更
- 冒頭 1-2 段落を、そのプロジェクトが何をするものかに書き換える
- 「テンプレートです」「複数案件へ横展開」など、雛形前提の説明を残さない
- セットアップ手順は実際の利用者向けに維持する

**`AGENTS.md`**

- タイトルは必要ならプロジェクトに合う運用名へ調整する
- 冒頭のリード文や導入指示に、展開前提・テンプレート前提の言い回しを残さない
- 必読順・ generated 編集禁止・ review ルールなど、運用上の強制事項は維持する

### Step 4: project.md の更新

`docs/context/project.md` を以下の方針で更新する:

**プロジェクト固有で書き換えるセクション:**

- Purpose → プロジェクトの概要・目的に書き換え（必要なら「Project Overview」等へ変更）
- Default In-Scope Features / Default Out of Scope → コンテキストから置き換え

**維持するセクション（テンプレート共通）:**

- Specification Workflow Entry → そのまま維持

### Step 5: Screen Map の更新

`docs/guides/frontend.md` の Screen Map and Access Guards セクションをプロジェクトのページ構成に合わせて更新する。

このステップはユーザーとの対話で進める:

1. テンプレートの初期 Screen Map（Auth 系ルート、Dashboard、Settings）を提示する
2. プロジェクトで追加/削除するページをユーザーに確認する
3. Settings Layout（サブナビ付きネストレイアウト）を使うかどうかを確認する
4. 各ページのレイアウトとガード（auth, verified, guest）の割り当てをユーザーに確認する
5. 確認結果に基づいてルートテーブルと、その直下のレイアウト/ガード方針を更新する

Auth 関連ルート（login, register, forgot-password 等）はテンプレートの Fortify 基盤が提供するため、原則維持する。

### Step 6: ポート設定の更新

ユーザー指定のポート番号で以下を更新:

- `docs/guides/infrastructure.md` の Local Runtime Topology テーブルと Default local endpoints
- `docs/guides/infrastructure.md` の Environment Variables テーブルの Default 列
- `.env.example` の該当変数

### Step 7: 確認と検証

1. 更新内容をユーザーに提示して承認を得る
2. `pnpm lint:docs` を実行して `docs/` 配下のリンク整合性を確認する
3. `README.md` と `AGENTS.md` を目視で確認し、リンク・タイトル・導入文にテンプレート感が残っていないかを確認する
4. コンテキストディレクトリに残った情報で反映漏れがないか最終チェック

## Output

更新完了後、以下を報告する:

- 更新したファイルの一覧と変更概要
- `README.md` と `AGENTS.md` のタイトル / リード文をどう更新したか
- コンテキストから抽出したが反映先が不明だった情報（あれば）
- 次のアクション推奨（追加確認が必要な項目があれば）

## Notes

- テンプレートの docs 構成（ディレクトリ構造、ファイル命名、役割分担）は変更しない
- 生成ファイル（`docs/generated/`）は絶対に編集しない
- `docs/` 内は英語、`README.md` は日本語のルールを維持する
- 既存のコーディングルールやアーキテクチャルールは上書きしない
- 追加情報は「これを削除すると AI が誤るか」で判断し、不要なら追加しない
- ルールの重複を避け、既存ファイルにリンクできる内容はリンクで済ませる
- 展開先では「テンプレートの説明」ではなく「このプロジェクトの説明」として読める文面にする

---
name: project-docs-init
description: 'テンプレートから展開した新規プロジェクトのdocsを初期化する。old_docs/等の既存コンテキストを読み取り、テンプレートのdocs構成に沿ってプロジェクト固有の情報を反映する。トリガー: 「docsを初期化」「プロジェクトdocs生成」「docs init」「ドキュメント初期化」'
---

# Project Docs Initialization

テンプレートリポジトリから展開した新規プロジェクトのドキュメントを初期化するスキル。

## Purpose

このテンプレートは Laravel + Inertia + Docker のひな形であり、展開先プロジェクトごとに `docs/` の内容をカスタマイズする必要がある。このスキルは既存のプロジェクトコンテキスト（`old_docs/` 等）を読み取り、テンプレートの docs 構成を維持しつつプロジェクト固有の情報を反映する。

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
| `docs/context/project.md` | プロジェクト概要、スコープ、リスク、未決定事項をプロジェクト固有の内容で置換 |
| `docs/guides/frontend.md` | Screen Map セクション（ルート一覧、Layout definitions、Middleware）をプロジェクトのページ構成に合わせて更新 |
| `docs/guides/infrastructure.md` | ポート番号を指定値に更新 |
| `.env.example` | ポート番号のデフォルト値を更新 |

以下のファイルはテンプレート共通のため**原則変更しない**:

- `docs/guides/backend.md` - アーキテクチャ・コーディングルール
- `docs/guides/frontend.md`（Screen Map 以外） - フロントエンドルール
- `docs/guides/common.md` - 共通コーディングスタイル
- `docs/context/architecture.md` - アーキテクチャ設計思想
- `docs/context/deploy.md` - デプロイ制約
- `docs/generated/boost.md` - 自動生成（編集禁止）
- `docs/generated/specs.md` - 自動生成（編集禁止）

### Step 3: project.md の更新

`docs/context/project.md` を以下の方針で更新する:

**プロジェクト固有で書き換えるセクション:**

- Template Purpose + Intended Use → プロジェクトの概要・目的に書き換え（セクション名も「Project Overview」等に変更）
- Default Scope Assumptions → コンテキストから In scope / Out of scope を生成
- Risks and Mitigation → 共通リスク3行を維持しつつ、プロジェクト固有リスクを追加
- Unresolved → コンテキストから未決定事項を抽出して記入

**維持するセクション（テンプレート共通）:**

- System Boundaries → そのまま維持（技術スタック・環境は同一）
- Design Trade-Offs → そのまま維持（アーキテクチャ判断は共通）
- Specification Workflow Entry → そのまま維持

### Step 4: Screen Map の更新

`docs/guides/frontend.md` の Screen Map and Access Guards セクションをプロジェクトのページ構成に合わせて更新する。

このステップはユーザーとの対話で進める:

1. テンプレートの初期 Screen Map（Auth 系ルート、Dashboard、Settings）を提示する
2. プロジェクトで追加/削除するページをユーザーに確認する
3. Settings Layout（サブナビ付きネストレイアウト）を使うかどうかを確認する
4. 各ページのレイアウトとガード（auth, verified, guest）の割り当てをユーザーに確認する
5. 確認結果に基づいてルートテーブル、Layout definitions、Middleware を更新する

Auth 関連ルート（login, register, forgot-password 等）はテンプレートの Fortify 基盤が提供するため、原則維持する。

### Step 5: ポート設定の更新

ユーザー指定のポート番号で以下を更新:

- `docs/guides/infrastructure.md` の Local Runtime Topology テーブルと Default local endpoints
- `docs/guides/infrastructure.md` の Environment Variables テーブルの Default 列
- `.env.example` の該当変数

### Step 6: 確認と検証

1. 更新内容をユーザーに提示して承認を得る
2. `pnpm lint:docs` を実行してリンク整合性を確認
3. コンテキストディレクトリに残った情報で反映漏れがないか最終チェック

## Output

更新完了後、以下を報告する:

- 更新したファイルの一覧と変更概要
- コンテキストから抽出したが反映先が不明だった情報（あれば）
- 次のアクション推奨（README.md の更新など）

## Notes

- テンプレートの docs 構成（ディレクトリ構造、ファイル命名、役割分担）は変更しない
- 生成ファイル（`docs/generated/`）は絶対に編集しない
- `docs/` 内は英語、`README.md` は日本語のルールを維持する
- 既存のコーディングルールやアーキテクチャルールは上書きしない

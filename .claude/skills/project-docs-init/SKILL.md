---
name: project-docs-init
description: テンプレートから展開した新規プロジェクトのdocsを初期化する
disable-model-invocation: true
---

# Project Docs Initialization

テンプレートリポジトリから展開した新規プロジェクトのドキュメントを初期化する。

## Purpose

このテンプレートは Laravel + Inertia + Docker のひな形であり、展開先プロジェクトごとに `docs/` の内容をカスタマイズする必要がある。既存のプロジェクトコンテキスト（`old_docs/` 等）を読み取り、テンプレートの docs 構成を維持しつつプロジェクト固有の情報を反映する。

重要:
- docs は短く保つ
- 推測できる一般論や長いチュートリアルは追加しない
- プロジェクト固有で、安定していて、コードだけでは分からない情報だけを残す

## Prerequisites

実行前に以下を確認する:

1. プロジェクトのコンテキスト情報が `old_docs/` ディレクトリ（または指定されたディレクトリ）にまとめられていること
2. テンプレートの `docs/` 構造がそのまま残っていること

## Step 1: Input 確認

ユーザーに以下を確認する:

1. **コンテキストディレクトリ**: プロジェクトの既存ドキュメントがある場所（デフォルト: `old_docs/`）
2. **ローカル識別子・ポート設定**: このプロジェクトで使う `APP_NAME`、`DB_DATABASE`、Docker ポート（複数プロジェクト並列開発のため、プロジェクトごとに変える運用）

## Step 2: コンテキスト読み取り

コンテキストディレクトリ内のすべてのファイルを読み取り、以下の情報を抽出する:

- プロジェクト名・概要
- 対象ユーザー・解決する課題
- 主要機能・スコープ
- 技術的な制約や特記事項
- 未決定事項

## Step 3: docs 更新対象の特定

テンプレートの docs 構成のうち、プロジェクト固有の情報で更新が必要なファイル:

| ファイル | 更新内容 |
| --- | --- |
| `AGENTS.md` | プロジェクトの目的、非自明な慣習、運用ルールを展開先前提の内容へ更新 |
| `docs/infrastructure.md` | Local Runtime Topology / Default local endpoints / runtime 事実 / 必要ならデプロイ前提を更新（識別子・DB 名・ポートの値そのものは `.env.example` が正典） |
| `docs/commands.md` | Interactive Browser Verification のローカル URL 例を更新 |
| `.env.example` | `APP_NAME`、`DB_DATABASE`、`VITE_APP_NAME`、ポート番号のデフォルト値を更新 |
| `public/manifest.webmanifest` | Web App Manifest の静的なアプリ名・説明を更新 |
| `README.md` | タイトル、冒頭のリード文、概要、セットアップをプロジェクト固有の文言へ更新 |

以下のファイルはテンプレート共通のため**原則変更しない**:

- `docs/generated/boost.md` - 自動生成（編集禁止）
- `docs/generated/specs.md` - 自動生成（編集禁止）
- `docs/testing-as-spec.md` - テスト=仕様 手法（template 共通）
- `docs/adr/template/**`、`docs/adr/README.md` - template ADR と ADR 採番規約（プロジェクト独自 ADR は `docs/adr/NNNN-*.md` に作成）

補足:
- `docs/commands.md` はコマンドのソースオブトゥルースなので、プロジェクト固有の URL 例以外は実際にコマンド差分がある場合だけ更新する
- `README.md` と `AGENTS.md` は構成や役割は維持するが、タイトルと導入文はテンプレート文言のまま残さない
- 「template」「starter」「ひな形」など、展開先で他人事に見える導入文は避ける

## Step 4: 入口ドキュメントの更新

`README.md` と `AGENTS.md` の先頭を以下の方針で更新する:

**`README.md`**

- タイトルをプロジェクト名またはプロジェクト名ベースの文言へ変更
- 冒頭 1-2 段落を、そのプロジェクトが何をするものかに書き換える
- 「テンプレートです」「複数案件へ横展開」など、雛形前提の説明を残さない
- セットアップ手順は実際の利用者向けに維持する

**`AGENTS.md`**

- プロジェクトの目的、守るべき非自明ルール、確認コマンドを展開先前提で更新する
- テンプレート前提の表現を残さない
- generated 編集禁止・review ルール・docs の責務分離など、運用上の強制事項は維持する

## Step 5: AGENTS.md のプロジェクト文脈更新

`AGENTS.md` の `Project Context` と関連セクションを以下の方針で更新する:

**プロジェクト固有で書き換える要素:**

- このアプリが何者で、誰に何を提供するか
- 何が初期スコープで、何が out of scope か
- コードだけでは伝わりにくい運用ルールや制約
- プロジェクト固有の review / verification の判断基準

**維持する要素（テンプレート共通）:**

- docs の責務分離
- generated ファイル編集禁止
- docs / README の言語ルール

必要なら、画面構成やアクセス制御のような補足は専用 doc を増やさず、まず `AGENTS.md` に短く要約して済ませる。

## Step 6: ローカル識別子・ポート設定の更新

ユーザー指定の `APP_NAME`、`DB_DATABASE`、ポート番号、およびコンテキストから抽出したプロジェクト名で以下を更新:

- `docs/infrastructure.md` の Local Runtime Topology テーブルと Default local endpoints
- `docs/commands.md` の Interactive Browser Verification にある Playwright CLI のローカル URL
- `README.md` のセットアップ後の既定 URL
- `.env.example` の `APP_NAME`、`DB_DATABASE`、`VITE_APP_NAME`、ポート設定の該当変数
- `public/manifest.webmanifest` の `name`、`short_name`、`description`

命名規則:
- `APP_NAME` はケバブケース
- `DB_DATABASE` はスネークケース

## Step 7: 確認と検証

1. 更新内容をユーザーに提示して承認を得る
2. `pnpm lint:docs` を実行して `docs/` 配下のリンク整合性を確認する
3. `README.md` と `AGENTS.md` を目視で確認し、リンク・タイトル・導入文にテンプレート感が残っていないかを確認する
4. コンテキストディレクトリに残った情報で反映漏れがないか最終チェック

## Output

更新完了後、以下を報告する:

- 更新したファイルの一覧と変更概要
- `README.md` と `AGENTS.md` のタイトル / リード文をどう更新したか
- コンテキストから抽出したが `AGENTS.md` / `README.md` / `docs/infrastructure.md` のいずれにも自然に置けなかった情報（あれば）
- 次のアクション推奨（追加確認が必要な項目があれば）

## Notes

- テンプレートの docs 構成は最小限を維持し、不要なファイルを増やさない
- 生成ファイル（`docs/generated/`）は絶対に編集しない
- `docs/` 内は英語、`README.md` は日本語のルールを維持する
- 追加情報は「これを削除すると AI が誤るか」で判断し、不要なら追加しない
- ルールの重複を避け、永続ルールは `AGENTS.md`、参照情報は `docs/` に寄せる
- 展開先では「テンプレートの説明」ではなく「このプロジェクトの説明」として読める文面にする

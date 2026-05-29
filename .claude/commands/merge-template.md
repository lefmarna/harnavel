---
description: template リポジトリからの pull で発生したコンフリクトを規約に沿って解消する
argument-hint: "[REMOTE=template] [BRANCH=main]"
---

# Merge Template Command

`git pull <remote> <branch>` で発生したコンフリクトを、アプリ固有/template 寄せの判断基準に沿って解消します。

## 解消方針

- **template 側に寄せる**: 共通基盤（auth, mail, settings 等）、scaffolding、テストヘルパー、命名・コーディング規約、ライブラリ更新、汎用 UI プリミティブ、テスト=仕様 手法のファイル（`docs/testing-as-spec.md`、template ADR `docs/adr/template/**`、`docs/adr/README.md`、arch test 雛形、カバレッジ/Infection 設定）
- **アプリ側を優先**: ドメインロジック、独自 UI コンポーネント / デザイントークン、独自 ViewModel / Service / Repository パターン、i18n や多言語テスト、アプリ固有のルート・コントローラ、番号付きアプリ ADR（`docs/adr/NNNN-*.md`、フラット。`docs/adr/README.md` は template 寄せ）
- **テスト**: 同一観点が両側にあれば統合または片方削除して可。**寄せる場合は template 側へ**。アプリ固有のカバレッジ（i18n テストなど）は保持

## 引数

- `REMOTE`: マージ対象のリモート名（既定: `template`）
- `BRANCH`: マージ対象のブランチ名（既定: `main`）

## 作業フロー

### 1. 事前確認

```bash
git status
git remote -v
```

- マージが進行中か (`MERGE_HEAD` の有無)
- 既に `git pull <REMOTE> <BRANCH>` 実行済みか
- 未実行なら実行する。失敗してコンフリクトが出た時点でこのフローへ

### 2. コンフリクト一覧化＆タスク管理

```bash
git diff --diff-filter=U --name-only
```

- 一覧を取得し、TaskCreate でファイル単位のタスクを起こす
- ファイル数が多い場合は関連グループ（auth 関連、settings 関連 等）でまとめて可

### 3. 各コンフリクトの解消

ファイルを開いて `<<<<<<< HEAD / ======= / >>>>>>> ...` ブロックを 1 つずつ解消する。

判断手順:

1. **両側の差分の意図を理解する**（ドキュメント・周辺コード・既存テストを読む）
2. **分類する**: `アプリ固有 / template 寄せ / ハイブリッド`
3. **ハイブリッドの場合**: アプリ固有の規約（例: 専用 ViewModel パターン、独自 Repository メソッド）を残しつつ、内部実装は template の方針を採用するなど折衷
4. **PII / セキュリティ関連の差分**: ベストプラクティス側を採用（例: ログから email を除外、defense-in-depth）
5. **解消後**、コンフリクトマーカーが残っていないことを確認

```bash
git grep -nI -E '^(<<<<<<<|=======|>>>>>>>)' -- . \
  ':(exclude)vendor/**' \
  ':(exclude)node_modules/**' \
  ':(exclude)public/build/**' \
  ':(exclude)storage/**' \
  ':(exclude)bootstrap/cache/**' \
  ':(exclude)docs/generated/**'
```

### 4. アプリ固有メソッド / 不要コードの整理

- template 寄せに合わせて使われなくなったメソッド（例: Repository の独自メソッド）は削除
- 関連するテストの fake / mock も併せて整理

### 5. 検証

プロジェクトの規約に沿ったコマンドを実行する。`CLAUDE.md` / `AGENTS.md` の Verification セクションを参照して選ぶ。

例（Laravel + Inertia/React アプリ）:
- Backend: `mise run verify:backend`、または必要に応じて `docker compose exec app ...`
- Frontend: `pnpm format:check` / `pnpm lint:check` / `pnpm types:check` / `pnpm test:e2e --reporter=dot`
- Docs: `pnpm lint:docs`

format check で warn が出たら `pnpm format` で自動整形してから再 check。

### 6. ステージングとサマリ

```bash
git add <resolved-files>
git status
git diff --cached --stat
```

- 全コンフリクトが解消され、`All conflicts fixed` 表示になっていることを確認
- **コミットは行わない**。ユーザーがレビューしてから自身でコミットする方針

### 7. レポート

ユーザーに以下を提示:

- **template 側を採用した部分**: ファイルと簡潔な理由
- **アプリ側を残した部分**: ファイルと簡潔な理由
- **テストの統廃合内容**: 削除したテスト、統合したテスト、保持した固有テスト
- **検証結果**: 各コマンドの pass 状況
- **upstream 報告候補**: コンフリクト解消中に template 側にもバグや改善点を発見した場合は、報告用の文面を併記

## 重要な原則

- **読み解いてから判断する**: 両側の差分の "意図" を周辺コードと突き合わせて理解する。表面的なテキストマージは避ける
- **defense in depth は維持**: セキュリティ系の二重チェックを「冗長」として安易に削らない
- **PII を残さない**: ログから email など個人情報を出すコードがあれば落とす方向で揃える
- **依存の暗黙化に注意**: DB collation 等の "設定への暗黙依存" がある場合は、プロジェクト規約で明示されているか確認
- **DRY と明示性のバランス**: アプリ規約があるならそれに従う（例: 専用 ViewModel パターンを使うアプリでは、template の inline 実装を ViewModel に置き換える）
- **環境ガードブロックは 1 つに**: `if (app()->environment(['local', 'testing']))` などの test-only ブロックが複数できないように統合する
- **コミット前に止まる**: 解消後はステージングまでで停止し、ユーザーレビューを待つ

## アプリ固有が template 起因の bug fix だった場合

差分が「template 側のバグを直す改善」だった場合:

1. 差分の妥当性を判断（アプリ固有事情がないか確認）
2. アプリ側で fix を維持しつつ、template への upstream 報告文面（issue / PR テンプレート）を提示する
3. 報告文面には: 概要・再現条件・原因・修正案コード・回帰防止テストを含める

## 使用例

```bash
# 標準（template リモート、main ブランチ）
/merge-template

# 異なるリモート / ブランチを指定
/merge-template REMOTE=upstream BRANCH=develop
```

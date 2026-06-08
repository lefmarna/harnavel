---
description: 派生アプリで育った最良版を template へ吸い上げ（上り集約）、以降の pull をゼロコンフリクト化する
argument-hint: "[REMOTE=template] [APPS=<app1> <app2> ...]"
---

# Absorb From Apps Command

`/merge-template`（template→アプリの**下り**）の対になる、**アプリ→template の上り集約**コマンド。
派生アプリ間で生じた差分のうち「アプリ固有でないもの」を最良版で template に吸い上げ、共有サーフェスを揃えて、以降の `git pull template main` を**判断不要（較正行のみのコンフリクト）**に保つ。

決定モデルは [`TADR-0003`](../../docs/adr/template/0003-shared-surface-convergence.md)（共有サーフェス収束）。このコマンドはその運用手順で、**template repo で実行する**。

## 原則（逸脱しないこと）

- **収束対象は3ティア**（TADR-0003）:
  - **T1 完全共有** — アプリ固有内容を持たないファイル → byte 一致を狙う。
  - **T2 ハイブリッド** — template scaffolding とアプリ内容が混在 → template 由来ハンクのみ収束し、アプリ拡張は残す（byte 一致しない）。
  - **T3 アプリ所有** — プレゼン/ブランディング/ドメイン/デプロイ設定 → 収束対象外。
- **アプリ優先で残す（＝統一しない）**: 較正値（coverage/mutation の `--min`・mutation のブロッキング可否・`@pest-mutate-ignore` の注釈密度）、ドメインロジック、デザイントークン、i18n、アプリ固有のルート/ドメイン/デプロイ設定。
- **選定はベストプラクティス基準**（頻度の多数決ではない）。**ハンク単位**で最良版を選ぶ。
- **等価変異は注釈より簡約で消す**（簡約不可で load-bearing な場合のみ `@pest-mutate-ignore`＋理由）。詳細は `docs/testing-as-spec.md`。
- **テストと実装は結合して動かす**: より網羅的な共有テストは、それを満たす実装が template 側に揃って初めて上げる。template が緑にできない spec は上げない。
- **小さく・検証・ステージ止め**: ファイル種別ごとに小さい PR、各 PR で検証、ステージまでで停止しユーザーがコミットする。
- **作業ログは残さない**: per-app チェックリストや具体的な差分メモは作業用（`/tmp` 等）に留め、repo には commit しない（一度きりの transient）。

## 引数

- `REMOTE`: 各アプリ側で template を指すリモート名（既定: `template`）。
- `APPS`: 対象の派生アプリ repo パス（空白区切り）。未指定ならユーザーに確認する（template repo からは見えないため必須）。

## 作業フロー

### 1. 前提確認

対象アプリのパス（`APPS`）を確定する（不明ならユーザーに聞く）。各アプリについて:

- **作業ツリーがクリーンであること**を必ず確認する。未コミットの WIP があると、次の棚卸しの `git diff <REMOTE>/main`（作業ツリー比較）がドリフトと WIP を混同し、**一時作業を template に誤って吸い上げる**恐れがある。dirty なら**停止**し、commit/stash を促す。あわせて、吸い上げ元として意図したブランチに居ることも確認する。
- クリーンなら `REMOTE` の最新を取得する。

```bash
dirty=0
for d in <APPS>; do
  st=$(git -C "$d" status --porcelain)
  if [ -n "$st" ]; then
    echo "✗ $d: 作業ツリーが dirty（commit/stash してから再実行）"
    echo "$st"
    dirty=1
    continue
  fi
  git -C "$d" fetch -q "$REMOTE"
  echo "✓ $d: clean / $(git -C "$d" rev-parse --abbrev-ref HEAD) @ $REMOTE/main=$(git -C "$d" rev-parse --short "$REMOTE/main")"
done
if [ "$dirty" -ne 0 ]; then exit 1; fi
```

### 2. ドリフト棚卸し（背骨ツール）

各アプリの `git diff <REMOTE>/main` が「次の pull で衝突する＝ドリフトした共有ファイル」を権威的に示す（**前提: step 1 で各アプリがクリーン**＝コミット済み状態であること。未コミット WIP があると作業ツリー比較に混ざるため必ず先に確認）。

```bash
# template(=このrepo) が追跡する全ファイル＝共有サーフェスの母集合
git ls-files > /tmp/tpl.txt
for d in <APPS>; do
  echo "## $d"
  git -C "$d" diff "$REMOTE/main" --name-only | sort > /tmp/app.txt
  comm -12 <(sort /tmp/tpl.txt) /tmp/app.txt   # 共有 かつ ドリフトあり
done
```

**consensus スキャン**（≥2アプリが互いに byte 一致 かつ template と相違＝「template が遅れている」上り候補の抽出）も有効。ただし**偽陽性に注意**:

- 自分が既に上げた分 → アプリが**下りで追従する側**（上り候補ではない）。
- アプリが単に古い分 → **下りで取得**すべき（上り候補ではない）。
- → git 履歴か内容で**ドリフトの向き**を判定してから扱う。

### 3. 分類（T1 / T2 / T3）

各差分ファイル・ハンクを3ティアに分類する。T3 とアプリ固有ハンクは対象外として除外する。

### 4. 最良版の選定（ハンク単位）

- 汎用改善・注釈は最良版を選ぶ。**等価変異は簡約で消すのが最上**（注釈より上位）。
- 同じ箇所を複数アプリが別形で直していたら **1つを canonical に選ぶ**（残りは下りで収束）。
- アプリ固有ハンク（独自 Params/ViewModel、ドメイン列、i18n、デザイン）は **template に流さない**。

### 5. template へ上り（小 PR）

- ファイル種別ごとに小さく。byte 一致が狙える T1 は最良版アプリと**厳密一致**で書く。T2 は template 由来ハンクのみ収束。
- 各 PR で変更領域に応じて検証: `mise run verify:backend` ／ `pnpm format:check && pnpm lint:check && pnpm types:check` ／ 必要なら scoped mutation（`docker compose exec -T app vendor/bin/pest --mutate --covered-only --class="..."`）／ `pnpm lint:docs` ／ workflow は YAML 検証。
- **コミットしない**。ステージまでで停止しユーザーレビューを待つ。

### 6. 下りへの引き継ぎ

template を push 後、各アプリは `git pull <REMOTE> main` + `/merge-template` で収束する。per-app の「**残すアプリ固有**」メモ（ドメイン/i18n/デザイン/較正）を作業用に用意し、`/merge-template` に添えて渡す（このメモは transient・commit しない）。

### 7. 収束確認（検証）

各アプリの pull/merge 後、再び `git diff <REMOTE>/main` で:

- 収束した T1 ファイルが **byte 一致**、T2 は共有ハンクの差分が消えていること。
- 残差が**較正行・アプリ固有のみ**であること。
- コンフリクトマーカー残存なし（`git -C "$d" grep -nI -E '<<<<<<<|>>>>>>>' -- . ':(exclude)vendor/**' ':(exclude)node_modules/**'`）。

## 使用例

```bash
# 4つの派生アプリから上り集約
/absorb-from-apps APPS="../app-one ../app-two ../app-three ../app-four"
```

## 関連

- 下り（template→アプリ）: [`merge-template`](merge-template.md)
- 決定モデル: [`TADR-0003`](../../docs/adr/template/0003-shared-surface-convergence.md)
- テスト規約・等価変異カタログ: `docs/testing-as-spec.md`

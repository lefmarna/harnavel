---
name: adr-compact
description: ADR を現在有効な判断だけに圧縮する。覆った判断、一時的な展開メモ、実装タスク、重複説明を削除・統合し、必要なら App ADR の番号も詰める。
disable-model-invocation: true
argument-hint: "[ADR paths or area]"
---

# ADR Compact

ADR を「将来の読者が再判断しないための durable decision」だけに戻す。履歴保存ではなく、現在有効な判断・理由・却下案を読みやすく保つための整理を行う。

## 事前確認

- `AGENTS.md`、`docs/adr/README.md`、`CONTEXT.md` を読む。
- 対象 ADR と、そこから参照される ADR / docs / tests / code を読む。
- Template ADR と App ADR を混同しない。
  - Template ADR: `docs/adr/template/NNNN-*.md`。template 所有。番号は再利用しない。
  - App ADR: `docs/adr/NNNN-*.md`。各アプリ所有。削除・統合で欠番が出たら、参照更新込みで番号を詰めてよい。

## 圧縮基準

残すもの:

- 現在有効な決定。
- その決定を再導出しないために必要な背景。
- 実際に検討され、今後も迷いそうな却下案。
- 変更時に守るべき結果や制約。

削るもの:

- 覆った判断。現在の判断に吸収し、古い判断本文や amendment 追記は残さない。
- 一時的な展開メモ、rollout 手順、Phase 実装計画、作業ログ、TODO。
- コードやテストが正典になっている細かい実装説明。
- `AGENTS.md` / `docs/` / skill / tests と重複する運用説明。
- 予想だけの future work。今は採用しない判断なら out of scope とだけ書く。
- 長いコード例。必要なら近い実装ファイルかテストを参照する。

## 削除・統合の判断

- 同じ判断を新しい ADR が完全に覆っているなら、古い ADR は削除または新しい ADR へ統合する。
- 古い判断が既に出荷され、他 ADR / docs / code の理解に必要なら `Superseded by ADR-NNNN` として短い tombstone を残す。
- Proposed のまま採用されなかった ADR は削除する。
- 単なる移動は tombstone を残さない。参照を新しい置き場所へ張り替えて旧ファイルを消す。

## 作業手順

1. 棚卸しする。
   - `wc -l docs/adr/README.md docs/adr/**/*.md`
   - `rg -n "Amended|Superseded|Deprecated|Phase|rollout|temporary|transient|TODO|future|later|deferred|一時|展開|覆" docs/adr`
2. 対象 ADR ごとに、現在有効な判断を 1 文で言い切れるか確認する。
3. 圧縮する。
   - `Context` は判断に必要な背景だけにする。
   - `Decision` は現在形で書く。
   - `Considered alternatives` は今後も迷いそうなものだけ残す。
   - `Consequences` は現在の制約・リスクだけ残す。
4. 削除・統合した場合は参照を更新する。
   - `rg -n "ADR[- ]?NNNN|TADR[- ]?NNNN|deleted-file-slug|old heading" .`
   - App ADR の番号を詰めた場合は、ファイル名と全参照を同時に更新する。
   - Template ADR の番号は詰めない。
5. 検証する。
   - `pnpm lint:docs`
   - `git diff --stat`
   - `git diff` で、判断の意味が変わっていないか、古い判断の追記だけが残っていないか確認する。

## 完了条件

- ADR は現在有効な判断だけを説明している。
- 覆った判断や一時的な展開都合が残っていない。
- 削除・統合・番号詰め後のリンクと参照が壊れていない。
- `pnpm lint:docs` が通っている。

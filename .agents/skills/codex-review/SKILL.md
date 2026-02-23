---
name: codex-review
description: 'Codex CLI（read-only）を用いて、レビュー→修正→再レビュー（ok: true まで）を反復し収束させるレビューゲート。仕様書/SPEC/PRD/要件定義/設計、実装計画（PLANS.md等）の作成・更新直後、major step（>=5 files / 新規モジュール / 公開API / infra・config変更）完了後、および git commit / PR / merge / release 前に使用する。キーワード: Codexレビュー, codex review, レビューゲート.'
---

# Codex Iterative Review Gate

## Flow

### Roles (fixed)

- Reviewer: Codex (fixed / read-only)
- Orchestrator (apply changes + request review): anyone (e.g., Claude Code / Antigravity / human)

> Codex does **review only**. The orchestrator applies fixes.

### Stateless operation

- Do not rely on chat history. Always save the latest report (`YYYYMMDD-HHMM_summary.md`) including the latest Codex JSON (especially `notes_for_next_review`), and use it as the next input.
- `/clear` is not required (use only after confirming the report is the only state).

Scale classification → scale-specific Codex review → orchestrator fixes → re-review (repeat until `ok: true`)

```
[scale] → small:  diff ─────────────────────────→ [fix loop]
       → medium: arch → diff ───────────────────→ [fix loop]
       → large:  arch → parallel diffs → cross-check ─→ [fix loop]
```

- Codex: the reviewer (always recommended to run as read-only)
- Fixes: orchestrator (human / other tools)

---

## Scale classification

### Metrics (definitions in this skill)

- `_tf` = number of changed files (tracked changes + untracked new files)
- `_ln` = number of changed lines (adds + dels)
  - tracked: sum of additions+deletions from `git diff --numstat`
  - untracked: count as “full additions” via `--no-index` diff against `/dev/null` (adds only)

> Goal: make the small/medium/large decision reproducible without hesitation.

### Commands (default: worktree vs HEAD)

```bash
diff_range="${diff_range:-HEAD}"
```

#### 1) List tracked changed files

```bash
git diff --name-only "$diff_range"
```

#### 2) Sum changed lines for untracked files (count as full additions)

```bash
git ls-files --others --exclude-standard   | while IFS= read -r f; do
      git diff --no-index --numstat /dev/null -- "$f" || true
    done   | awk '($1 ~ /^[0-9]+$/ && $2 ~ /^[0-9]+$/) {add+=$1; del+=$2} END {print add+del}'
```

#### 3) `_tf` (changed file count = tracked + untracked, de-duplicated)

```bash
{ git diff --name-only "$diff_range"; git ls-files --others --exclude-standard; }   | sed '/^$/d' | sort -u | wc -l
```

#### 4) Tracked `_ln` (adds + dels)

```bash
git diff --numstat "$diff_range"   | awk '($1 ~ /^[0-9]+$/ && $2 ~ /^[0-9]+$/) {add+=$1; del+=$2} END {print add+del}'
```

- `--numstat` outputs “added lines” and “deleted lines” per file
- Exclude binary lines where values are `-` (filtered out by the awk condition)

#### 5) Untracked `_ln` (full-additions via `/dev/null → file`)

```bash
git ls-files --others --exclude-standard   | while IFS= read -r f; do
      git diff --no-index --numstat /dev/null -- "$f"
    done   | awk '($1 ~ /^[0-9]+$/ && $2 ~ /^[0-9]+$/) {add+=$1; del+=$2} END {print add+del}'
```

> This treats untracked files as diff-based “additions”, so line counting aligns with tracked diffs.

#### 6) `_ln` (tracked + untracked)

Add (4) and (5). Example:

```bash
tracked_ln=$(git diff --numstat "$diff_range" | awk '($1 ~ /^[0-9]+$/ && $2 ~ /^[0-9]+$/) {add+=$1; del+=$2} END {print add+del}')
untracked_ln=$(git ls-files --others --exclude-standard | while IFS= read -r f; do git diff --no-index --numstat /dev/null -- "$f"; done | awk '($1 ~ /^[0-9]+$/ && $2 ~ /^[0-9]+$/) {add+=$1; del+=$2} END {print add+del}')
ln=$((tracked_ln + untracked_ln))
echo "$ln"
```

---

### Scale rules (AND)

| Scale  | Thresholds (AND)                      | Strategy                            |
| ------ | ------------------------------------- | ----------------------------------- |
| small  | `_tf <= 3` **and** `_ln <= 100`       | diff                                |
| medium | `_tf <= 10` **and** `_ln <= 500`      | arch → diff                         |
| large  | otherwise (either threshold exceeded) | arch → parallel diffs → cross-check |

If `diff_range` is omitted, use `HEAD` and review uncommitted worktree changes (worktree vs HEAD). Do not distinguish staged vs unstaged.

---

## Handling untracked (new) files

Untracked files are **included** in review as full additions.

- Untracked list: `git ls-files --others --exclude-standard`
- Generate a single-file addition diff: `git diff --no-index /dev/null -- path/to/file`

**For large:**

- Parallelism: 3–5 sub-agents; each sub-agent handles only its assigned files
- Split: max 5 files / 300 lines per call; split by directory (cross-cutting concerns are caught in cross-check)
- Integration (cross-check) is performed by Codex (review only)

---

## Review policy (strict mode)

**Severity (2 levels):**

- **blocking**: must fix. Any blocking issue ⇒ `ok: false`.
  - bugs, security risks, spec mismatches (Must fix)
  - **clear maintainability/readability degradation that will obviously lead to bugs or make changes difficult (Should fix)**
- **advisory**: recommendation/warning. Does not block `ok: true`.
  - preference, future improvement, minor style (Nice to have)

**Rules:**

- Each issue must be **one discrete, actionable change**
- Do **not** treat “might break something” as a bug unless the impact is **certain and demonstrated**
- No rename suggestions
- Missing tests must be pointed out (put in an issue with `category: testing`; include commands when possible)
  - Minimal repo verification: `pnpm test:e2e --project=chromium`
- CI handling:
  - “CI will definitely break” based on the diff (obvious config mistakes, etc.) → `blocking`
  - “CI might fail” without evidence → `advisory` (keep short; don’t invent)

---

## Fix loop

If `ok: false`, iterate up to `max_iters`:

1. Parse Codex `issues` → make a fix plan (prioritize fastest convergence)
2. Orchestrator applies fixes (**minimal diffs**, no spec changes)
   - If a fix is unclear, you may ask Codex for additional review-oriented guidance (but you still apply the change yourself)
   - If the trade-off is large, write it as “質問: ...” in `problem` and escalate to a human
3. Minimal verification: `pnpm test:e2e --project=chromium`
4. Ask Codex to re-review (include previous `notes_for_next_review`)

Stop when:

- `ok: true`
- `max_iters` reached
- tests fail twice in a row (summarize status and escalate to a human)

### When to escalate to a human

- Spec/intent judgment is needed (product intent unclear)
- Trade-offs are real and no option is clearly better
- Requires actions outside the repo (external services/env/files)

---

## Stateless operation (do not depend on /clear)

This skill is designed so the **report is the single source of truth**.

- After each review run, save a report containing **Codex JSON (`ok/summary/issues/notes_for_next_review`)**
  - Save regardless of `ok:true/false` (so you can resume even if interrupted)
- For re-review, take `notes_for_next_review` from the latest report and include it in the next Codex prompt
- This preserves consistent convergence behavior across different orchestrators/tools and regardless of `/clear`

---

## Running Codex

```bash
codex exec --sandbox read-only "<PROMPT_REVIEW>"
```

- Pass the final prompt (including the JSON schema)
- The orchestrator must explicitly list the relevant file paths
- Wait for completion (mandatory): do not start other tasks while `codex exec` is running
  - Polling: every 60 seconds up to 20 times; log only `poll i/20` and elapsed time; do nothing else
  - If still not finished after 20 polls: treat as “timeout” and follow the error rules

---

## Codex output schema

Require Codex to output **exactly one JSON object**. The orchestrator appends the schema and field notes below to the end of the prompt.

**Human-readable fields must be Japanese**: `summary`, `problem`, `recommendation`, `notes_for_next_review`.

```json
{
  "ok": true,
  "phase": "arch|diff|cross-check",
  "summary": "レビューの要約",
  "issues": [
    {
      "severity": "blocking",
      "category": "security",
      "file": "src/auth.py",
      "lines": "42-45",
      "problem": "問題の説明（必要なら“質問:”で意図確認を促す）",
      "recommendation": "修正案（必要ならテスト案/コマンド例もここに）"
    }
  ],
  "notes_for_next_review": "次回レビューに引き継ぐ短いメモ（10行以内推奨）"
}
```

Field notes:

- `ok`: true iff there are zero blocking issues; false if one or more exist
- `severity`: 2 levels
  - **blocking**: must fix (bugs, security, spec mismatch, or clearly harmful maintainability/readability). Any blocking ⇒ `ok: false`
  - advisory: recommendation/warning (preference, future work). May exist even when `ok: true`
- `category`: correctness / security / perf / maintainability / testing / style
- `issues`: ideally 1 issue = 1 action; keep `lines` as tight as possible
- `notes_for_next_review`: short handoff memo to include in the next prompt (recommended ≤10 lines)

---

## Prompt templates

### arch

```
Review the architecture consistency of the following change. Output exactly one JSON object. See schema at the end.

This is executed as a review gate. If there is even one blocking issue, set ok=false; the process assumes we will fix and re-review until it converges.

All human-readable strings in JSON (summary/problem/recommendation/notes_for_next_review) must be written in Japanese.

Rules:
- Each issue must be one discrete, actionable change
- Do not mark “might break something” as a bug unless you can demonstrate certain impact
- If maintainability/readability has clearly degraded in a way that will obviously cause bugs or make changes difficult, mark it as blocking
- Missing tests must be pointed out (category: testing)
- CI: definitely broken → blocking / speculative CI-only concerns → advisory (keep short; don’t invent)

diff_range: {diff_range}
Focus: dependencies, responsibility boundaries, breaking changes, security design
Previous notes: {notes_for_next_review}
```

### diff

```
Review the following change. Output exactly one JSON object. See schema at the end.

This is executed as a review gate. If there is even one blocking issue, set ok=false; the process assumes we will fix and re-review until it converges.

All human-readable strings in JSON (summary/problem/recommendation/notes_for_next_review) must be written in Japanese.

Rules:
- Each issue must be one discrete, actionable change
- Do not mark “might break something” as a bug unless you can demonstrate certain impact
- If maintainability/readability has clearly degraded or logic is overly complex in a way that will obviously cause bugs or make changes difficult, mark it as blocking (exclude trivial whitespace that auto-formatting fixes)
- No rename suggestions
- Missing tests must be pointed out (category: testing). If possible, propose tests assuming `pnpm test:e2e --project=chromium`
- CI: definitely broken → blocking / speculative CI-only concerns → advisory (keep short; don’t invent)
- If context/spec trade-offs are missing, write “質問: ...” in `problem` and recommend human confirmation

diff_range: {diff_range}
Targets: {target_files}
Order of focus: correctness → security → impact → quality(maintainability) → perf → tests (high → low)
Previous notes: {notes_for_next_review}
```

### cross-check

```
Integrate parallel review results and perform a cross-cutting review. Output exactly one JSON object. See schema at the end.

This is executed as a review gate. If there is cross-cutting blocking (e.g., interface mismatch, missing authorization, API compatibility breaks), set ok=false.

All human-readable strings in JSON (summary/problem/recommendation/notes_for_next_review) must be written in Japanese.

Rules:
- Group issues by cross-cutting root cause and express them as concrete actions
- Avoid speculation; make blocking only when inconsistency is certain
- Missing tests must be pointed out (category: testing)

Overall stat: {stat_output}
Group results: {group_jsons}
Focus: interface consistency, error handling consistency, auth, API compatibility, test coverage
Previous notes: {notes_for_next_review}
```

---

## Common error rules

If `codex exec` fails (timeout / API outage / other):

1. Retry once (for timeouts, split the file set in half)
2. If it fails again, skip the phase and record the reason in the report
3. If arch is skipped: continue with diff only
4. If diff is skipped: mark that file set as “unreviewed” in the report

---

## Parameters

| Arg         | Default          | Description                       |
| ----------- | ---------------- | --------------------------------- |
| max_iters   | 5                | Max iterations (cap at 5)         |
| diff_range  | HEAD             | Range (default: worktree vs HEAD) |
| parallelism | 3                | Parallelism for large (cap at 5)  |
| report_dir  | docs/review-gate | Where to save stateless reports   |

---

## 終了レポート（ステートレスな唯一の状態）

保存先: `{report_dir}/YYYYMMDD-HHMM_summary.md`

````
## Codexレビュー結果
- diff_range: HEAD（作業ツリー vs HEAD、untrackedは全文追加）
- 指標: _tf=12, _ln=620
- 規模: large（_tf/_lnに基づく）
- 反復: 2/5 / ステータス: ✅ ok

### （実装エージェント） 最終変更サマリ
- auth.py: 認可チェック追加
- e2e: 追加テスト作成

### 実行した検証
- pnpm test:e2e --project=chromium

### Advisory（参考）
- ci.yml: 将来的なCI拡張への懸念（blockingではない）

### 未レビュー（エラー時のみ）
- utils/legacy.py: Codexタイムアウト、手動確認推奨

### 人に確認が必要（あれば）
- 質問: 〜（仕様/トレードオフ/リポジトリ外操作）

### Codex 最終JSON（このブロックが次回の唯一の入力ソース）
```json
{...}
```
````

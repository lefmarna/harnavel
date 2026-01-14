---
name: 'demand-definition'
description: 'A skill to pressure-test new feature ideas and identify the truly necessary requirements. Progress is managed step-by-step in JSON. Triggers: 「壁打ちしたい」「機能を考えたい」「要件を整理したい」「この機能について相談」「新機能のアイデア」「要求定義」「要件定義」'
allowed-tools: 'Read, Write, Edit, Glob, Grep, Task, mcp__serena__find_file, mcp__serena__get_symbols_overview, mcp__serena__find_symbol, mcp__serena__search_for_pattern, mcp__serena__list_dir'
---

# Demand Definition - Requirements Discovery & Specification

A skill to pressure-test new feature ideas and proceed step-by-step through requirements definition, up to generating UI design prompts.

## Core Principles

1. **Don’t jump to solutions** — first dig into “why?”
2. **Complete each step** — track progress in JSON
3. **Leverage existing assets** — inspect the codebase and consider alternatives
4. **Code-first investigation** — **in every phase, understand the current code before asking questions**

## Operating Policy (CRITICAL)

**At the start of every step, always execute in this order:**

1. ✅ **Read domain knowledge docs** (highest priority)
   - Check documents under `docs/domain/`
   - Read docs related to the screens the user mentions (e.g., `task-board.md`, `plan.md`, `progress.md`)
   - Understand the application’s overall features, goals, and use cases

2. ✅ **Investigate the code** (before asking the user)
   - Identify the relevant screens, features, and components mentioned by the user
   - Use `mcp__serena__*` tools to understand the current implementation
   - Check existing patterns, constraints, and similar features

3. ✅ **Remove unnecessary questions based on findings**
   - Don’t ask what can be learned from docs/code
   - Treat already-implemented features as given
   - Propose within current constraints

4. ✅ **Ask only what truly cannot be known**
   - User intent not inferable from docs/code
   - Business requirements and priorities
   - Future expansion plans

**Bad examples:**

- ❌「現在の画面構成はどうなっていますか？」→ ドメイン知識ドキュメント・コードを見れば分かる
- ❌「どんな状態がありますか？」→ ドメイン知識ドキュメント・コードを見れば分かる
- ❌「既存の類似機能はありますか？」→ ドメイン知識ドキュメント・コードを検索すれば分かる
- ❌「この画面の目的は何ですか？」→ ドメイン知識ドキュメントに記載されている

**Good examples:**

- ✅ ドメイン知識ドキュメント・コードから現状を把握 → 「`docs/domain/progress.md`を確認しました。現在は3カラム構成（未着手/進行中・保留/完了）ですが、スクロール問題が課題とのこと。どのくらいの頻度で困っていますか？」
- ✅ ドメイン知識ドキュメント・既存実装を確認 → 「ドメイン知識ドキュメントと既存実装を確認しましたが、完了タスクのトグル機能は見つかりませんでした。完了タスクを確認したい頻度はどのくらいですか？」

## Workflow

See `.kiro/requirements/` for the complete 5-step cc-sdd workflow:

1. **Purpose** - Clarify core problem and value
2. **Alternatives** - Validate necessity via codebase analysis
3. **Scope** - Define MVP vs. Nice-to-Have vs. Future
4. **Requirements** - Document user-facing requirements
5. **UI Design** - Generate UI implementation prompt

Detailed guides are in `.kiro/requirements/references/`.

## Starting a Session

1. **Determine session name**: `YYYY-MM-DD-feature-name`
   - Example: `2026-01-11-add-login-feature`

2. **Create session directory**
   - `mkdir -p .kiro/requirements/sessions/{session_name}`

3. **Initialize session.json**
   - Create `.kiro/requirements/sessions/{session_name}/session.json`
   ```json
   {
     "sessionId": "{session_name}",
     "status": "in_progress",
     "currentStep": 1,
     "steps": {
       "1_purpose": false,
       "2_alternatives": false,
       "3_scope": false,
       "4_requirements": false,
       "5_ui_prompt": false
     },
     "createdAt": "{current_timestamp_iso8601}",
     "updatedAt": "{current_timestamp_iso8601}"
   }
   ```

4. **Initialize discussion log**
   - Create `.kiro/requirements/sessions/{session_name}/discussion_log.md`
   ```markdown
   # Requirements Session: {session_name}
   Status: In Progress
   Current Step: 1
   ```

5. **Begin Step 1**
   - Read `.kiro/requirements/references/step1_purpose.md`
   - Perform preliminary codebase investigation
   - Start dialogue with user

**During session:**
- Complete each step per completion criteria
- Output deliverables: `1_purpose.md`, `2_alternatives.md`, etc.
- Log all discussions to `discussion_log.md` per `discussion_logger.md`
- Update `session.json` on each step completion (see `session_progress.md`)
- Show progress display at each step start

# Requirements Definition (cc-sdd)

**cc-sdd (Content-Centric Software Demand Definition)** - A systematic approach to requirements definition through iterative dialogue.

## 5-Step Process

1. **Purpose** - Clarify core problem and value
2. **Alternatives** - Validate necessity via codebase analysis
3. **Scope** - Define MVP vs. Nice-to-Have vs. Future
4. **Requirements** - Document user-facing requirements
5. **UI Design** - Generate UI implementation prompt

## Usage

Use `/cc-sdd` workflow to start a new session. See `references/` for step-by-step guides.

## Directory Structure

- `references/` - Step guides and templates
- `sessions/` - Session logs, progress tracking, and outputs
  - `{session_name}/session.json` - Progress tracking
  - `{session_name}/discussion_log.md` - Dialogue history (**gitignored** - may contain sensitive info)
  - `{session_name}/1_purpose.md` - Step outputs (include decision summaries for traceability)

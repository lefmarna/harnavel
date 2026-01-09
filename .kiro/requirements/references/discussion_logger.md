# Discussion Logger

How and when to record discussions to `discussion_log.md`.

**Note**: `discussion_log.md` is gitignored (may contain sensitive info). Include decision summaries in step outputs (`1_purpose.md`, etc.) for traceability.

## Critical Rules

1. **Never edit or delete existing content** - Preserve history
2. **Always append to bottom** - Chronological order
3. **Log changes as separate entries** - Keep both initial and revised decisions
4. **Use edit tool, never overwrite** - Append-only mode

## When to Log

Record an entry when:
- User provides answers
- Important proposal is made
- Decision is reached
- Step is completed
- Significant codebase finding emerges

## What to Log

- User statements (verbatim quote)
- Agent proposals/questions
- Decisions (adopted/rejected)
- Rationale for decisions
- Key codebase investigation results

## Logging Process

### 1. Read Format
```bash
Read: .kiro/requirements/references/discussion_log_format.md
```

### 2. Read Existing Log
```bash
Read: .kiro/requirements/sessions/{session_name}/discussion_log.md
```

### 3. Generate Entry
Follow format from `discussion_log_format.md`:
- Standard entry for discussions
- Change log entry for revisions

### 4. Append to File
```bash
# GOOD: Append with edit tool
Edit discussion_log.md:
  old: [last few lines of file]
  new: [last few lines]\n\n[new entry]

# BAD: Overwrite entire file
Write discussion_log.md: [entire content + new entry]
```

## Pre-Append Checklist

- [ ] Read `discussion_log_format.md`
- [ ] Read existing `discussion_log.md`
- [ ] No edits/deletions to existing content
- [ ] Strict format compliance
- [ ] Using edit tool (not write tool)
- [ ] Correct timestamp
- [ ] Added separator `---`

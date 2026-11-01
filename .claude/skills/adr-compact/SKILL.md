---
name: adr-compact
description: Compact ADRs down to the decisions that are currently in force. Remove or merge superseded decisions, temporary rollout notes, implementation tasks, and duplicate explanations, and renumber in-scope ADRs when needed. In derived repositories, compact only App ADRs; copied Template ADRs are read-only context and never compaction targets.
disable-model-invocation: true
argument-hint: "[ADR paths or area]"
---

# ADR Compact

Read `.agents/skills/adr-compact/SKILL.md` and follow it, resolving relative paths from that file's directory.
This file only registers the Claude frontmatter, which a symlink would lose; keep every instruction
in that file so the two copies cannot diverge.

---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [BRANCH=main]"
---

# Merge Template

Read `.agents/skills/merge-template/SKILL.md` and follow it, resolving relative paths from that file's directory.
This file only registers the Claude frontmatter, which a symlink would lose; keep every instruction
in that file so the two copies cannot diverge.

---
name: absorb-from-apps
description: Absorb the best app-grown changes back into the template and keep future downstream pulls conflict-light.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [APPS=<app1> <app2> ...]"
---

# Absorb from Apps

Read `.agents/skills/absorb-from-apps/SKILL.md` and follow it, resolving relative paths from that file's directory.
This file only registers the Claude frontmatter, which a symlink would lose; keep every instruction
in that file so the two copies cannot diverge.

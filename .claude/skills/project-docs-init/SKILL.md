---
name: project-docs-init
description: Initialize docs for a new project expanded from the template.
disable-model-invocation: true
---

# Project Docs Initialization

Read `.agents/skills/project-docs-init/SKILL.md` and follow it, resolving relative paths from that file's directory.
This file only registers the Claude frontmatter, which a symlink would lose; keep every instruction
in that file so the two copies cannot diverge.

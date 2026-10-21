---
name: absorb-from-apps
description: Absorb the best app-grown changes back into the template and keep future downstream pulls conflict-light.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [APPS=<app1> <app2> ...]"
---

# Absorb from Apps

Read `.agents/skills/absorb-from-apps/SKILL.md` and follow it. That file is the canonical body of this skill, shared
with every agent toolchain that works this repository. Skill-local paths it gives, such as
`references/`, resolve next to that file, not next to this one.

This file exists only to register the skill with the Claude-specific frontmatter above, which
a symlink would destroy. It carries no instructions of its own — never add any here, so the
two copies cannot diverge.

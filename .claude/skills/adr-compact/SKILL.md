---
name: adr-compact
description: Compact ADRs down to the decisions that are currently in force. Remove or merge superseded decisions, temporary rollout notes, implementation tasks, and duplicate explanations, and renumber in-scope ADRs when needed. In derived repositories, compact only App ADRs; copied Template ADRs are read-only context and never compaction targets.
disable-model-invocation: true
argument-hint: "[ADR paths or area]"
---

# ADR Compact

Read `.agents/skills/adr-compact/SKILL.md` and follow it. That file is the canonical body of this skill, shared
with every agent toolchain that works this repository. Skill-local paths it gives, such as
`references/`, resolve next to that file, not next to this one.

This file exists only to register the skill with the Claude-specific frontmatter above, which
a symlink would destroy. It carries no instructions of its own — never add any here, so the
two copies cannot diverge.

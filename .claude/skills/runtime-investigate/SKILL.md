---
name: runtime-investigate
description: Investigate a deployed environment on XServer — which release is live, HTTP health, web access and error logs, and Cron entries. Use only when diagnosing something already deployed; local development and local test failures are out of scope. Application logs are out of reach and every operation is read-only.
disable-model-invocation: true
argument-hint: "[environment or symptom]"
---

# Runtime Investigate

Read `.agents/skills/runtime-investigate/SKILL.md` and follow it. That file is the canonical body of this skill, shared
with every agent toolchain that works this repository. Skill-local paths it gives, such as
`references/`, resolve next to that file, not next to this one.

This file exists only to register the skill with the Claude-specific frontmatter above, which
a symlink would destroy. It carries no instructions of its own — never add any here, or the
two copies diverge again.

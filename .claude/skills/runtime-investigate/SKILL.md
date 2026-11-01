---
name: runtime-investigate
description: Investigate a deployed environment on XServer — which release is live, HTTP health, web access and error logs, and Cron entries. Use only when diagnosing something already deployed; local development and local test failures are out of scope. Application logs are out of reach and every operation is read-only.
disable-model-invocation: true
argument-hint: "[environment or symptom]"
---

# Runtime Investigate

Read `.agents/skills/runtime-investigate/SKILL.md` and follow it, resolving relative paths from that file's directory.
This file only registers the Claude frontmatter, which a symlink would lose; keep every instruction
in that file so the two copies cannot diverge.

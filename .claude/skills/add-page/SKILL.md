---
name: add-page
description: Use when adding a new Laravel + Inertia React page. Create the route, controller, PageViewModel, Wayfinder, React page, Pest Feature spec, and Playwright smoke E2E consistently, and verify the props boundary.
disable-model-invocation: true
argument-hint: "<page-name>"
---

# Add Page

Read `.agents/skills/add-page/SKILL.md` and follow it. That file is the canonical body of this skill, shared
with every agent toolchain that works this repository. Skill-local paths it gives, such as
`references/`, resolve next to that file, not next to this one.

This file exists only to register the skill with the Claude-specific frontmatter above, which
a symlink would destroy. It carries no instructions of its own — never add any here, or the
two copies diverge again.

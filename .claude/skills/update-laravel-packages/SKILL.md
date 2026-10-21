---
name: update-laravel-packages
description: Update this Laravel template's Composer, Laravel Installer, Node, pnpm, PHP/JavaScript dependencies, lockfiles, Boost resources, and application skeleton from a freshly generated Laravel reference project. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary Composer, pnpm, Laravel, dependency, or package-update work.
disable-model-invocation: true
---

# Update Laravel Packages

Read `.agents/skills/update-laravel-packages/SKILL.md` and follow it. That file is the canonical body of this skill, shared
with every agent toolchain that works this repository. Skill-local paths it gives, such as
`references/`, resolve next to that file, not next to this one.

This file exists only to register the skill with the Claude-specific frontmatter above, which
a symlink would destroy. It carries no instructions of its own — never add any here, so the
two copies cannot diverge.

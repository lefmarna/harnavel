---
name: project-docs-init
description: Initialize docs for a new project expanded from the template.
disable-model-invocation: true
---

# Project Docs Initialization

Initialize documentation for a new project expanded from the template repository.

## Purpose

This template is a Laravel + Inertia + Docker starter, so each derived project needs its own customized `docs/` content. Read the existing project context, such as `old_docs/`, and reflect project-specific information while preserving the template docs structure.

Important:

- Keep docs short.
- Do not add inferable generalities or long tutorials.
- Keep only information that is project-specific, stable, and not obvious from code alone.

## Prerequisites

Confirm the following before running:

1. Project context information is collected in the `old_docs/` directory, or another specified directory.
2. The template `docs/` structure is still intact.

## Step 1: Confirm Inputs

Ask the user to confirm:

1. **Context directory**: where the project's existing documentation lives. Default: `old_docs/`.
2. **Local identifiers and port settings**: `APP_NAME`, `DB_DATABASE`, and Docker ports used by this project. These are changed per project so multiple projects can be developed in parallel.

## Step 2: Read Context

Read every file in the context directory and extract:

- Project name and summary.
- Target users and problems solved.
- Major features and scope.
- Domain terms, as initial entry candidates for `CONTEXT.md`.
- Technical constraints and notable facts.
- Undecided items.

## Step 3: Identify Docs to Update

Files in the template docs structure that need project-specific updates:

| File | Update |
| --- | --- |
| `AGENTS.md` | Update the project purpose, non-obvious conventions, and operational rules for the derived project. |
| `CONTEXT.md` | Fill initial entries with domain terms extracted from context. Preserve the opening contract text and entry format. |
| `docs/infrastructure.md` | Update Local Runtime Topology, Default local endpoints, runtime facts, and deployment assumptions if needed. The actual identifier, DB name, and port values are canonical in `.env.example`. |
| `docs/commands.md` | Update the local URL examples in Interactive Browser Verification. |
| `.env.example` | Update default values for `APP_NAME`, `DB_DATABASE`, `VITE_APP_NAME`, and port numbers. |
| `public/manifest.webmanifest` | Update the static app name and description in the Web App Manifest. |
| `README.md` | Update the title, opening lead, overview, and setup text for the project. |

The following files are template-shared and should generally not be changed:

- `docs/generated/boost.md` - generated; do not edit.
- `docs/generated/specs.md` - generated; do not edit.
- `docs/testing-as-spec.md` - testing-as-spec method; shared by the template.
- `docs/adr/template/**`, `docs/adr/README.md` - template ADRs and ADR numbering policy. Project-specific ADRs are created as `docs/adr/NNNN-*.md`.

Notes:

- `docs/commands.md` is the command source of truth, so update it only for project-specific URL examples unless the actual commands differ.
- Preserve the structure and role of `README.md` and `AGENTS.md`, but do not leave the title and introduction as template wording.
- Avoid introductory text that reads like someone else's template after expansion, such as "template", "starter", or "scaffold".

## Step 4: Update Entry Documents

Update the top of `README.md` and `AGENTS.md` with the following policy:

**`README.md`**

- Change the title to the project name or project-name-based wording.
- Rewrite the first one or two paragraphs to describe what the project does.
- Do not leave scaffold-oriented explanations such as "this is a template" or "for horizontal rollout across multiple projects".
- Keep setup steps for actual users.

**`AGENTS.md`**

- Update the project purpose, non-obvious rules, and verification commands for the derived project.
- Do not leave template-assumption wording.
- Keep mandatory operational rules such as generated-file edit bans, review rules, and docs responsibility separation.

## Step 5: Update Project Context in AGENTS.md

Update `Project Context` and related sections in `AGENTS.md` using this policy:

**Project-specific elements to rewrite:**

- What this app is and what it provides to whom.
- What is in the initial scope and what is out of scope.
- Operational rules and constraints that are hard to infer from code alone.
- Project-specific review and verification judgment criteria.

**Elements to preserve because they are template-shared:**

- Docs responsibility separation.
- Generated-file edit ban.
- Language rules for docs and README.

If supplemental details such as screen structure or access control are needed, first summarize them briefly in `AGENTS.md` instead of adding a dedicated doc.

## Step 6: Update Local Identifiers and Port Settings

Using the user-provided `APP_NAME`, `DB_DATABASE`, port numbers, and the project name extracted from context, update:

- Local Runtime Topology table and Default local endpoints in `docs/infrastructure.md`.
- Playwright CLI local URL in Interactive Browser Verification in `docs/commands.md`.
- Default URL after setup in `README.md`.
- Relevant `APP_NAME`, `DB_DATABASE`, `VITE_APP_NAME`, and port variables in `.env.example`.
- `name`, `short_name`, and `description` in `public/manifest.webmanifest`.

Naming rules:

- `APP_NAME` is kebab-case.
- `DB_DATABASE` is snake_case.

Deployment settings are not files and are therefore outside this skill's edits. Tell the user which
per-environment GitHub settings the first deployment needs, for Develop and Production separately:

- `XSERVER_*` and `DEPLOY_DIRECTORY` secrets, and the `HEALTHCHECK_URL` secret (the canonical HTTPS
  URL ending at the Laravel health route, such as `https://example.com/up`; `delivery.yml` validates
  it before touching the server, and rejects a path that would not reach PHP).
- `HEALTHCHECK_BASIC_AUTH`, only for an environment whose document root is protected by Basic
  authentication. Both verification probes then send it; without it they receive 401 and a healthy
  release is withdrawn.

Also tell the user to **measure each environment's web SAPI OPcache directives** before relying on
the deployment, following "OPcache Revalidation Measurement" in `docs/infrastructure.md`, and to
record the values in that file's table. Release verification proves that *a* release answers, not
that *this* deployment's bytecode does
([`TADR-0006`](../../../docs/adr/template/0006-planned-maintenance-deployments.md)). It closes that
gap by sleeping `SETTLE_MS` (`scripts/verify-release-health.mjs`) before its first probe, so an
environment is only covered when all three hold:

- `opcache.validate_timestamps = 1`
- no `opcache.preload`
- `opcache.revalidate_freq` at or below `SETTLE_MS`

Do not substitute "the post-deploy sequence takes longer than the window" — nothing bounds that
duration from below, so it cannot carry the guarantee. An environment failing any of the three
accepts the previous release's `200` while the new schema is already migrated, and nothing in the
workflow detects it. Treat an unmeasured environment as unknown, not as covered by the template's
baseline.

## Step 7: Review and Verify

1. Present the updates to the user and get approval.
2. Run `pnpm lint:docs` to verify link consistency under `docs/`.
3. Review `README.md` and `AGENTS.md` visually and confirm links, titles, and introductions no longer feel like the template.
4. Do a final check for context-directory information that was not reflected.

## Output

After finishing, report:

- List of updated files and summary of changes.
- How the `README.md` and `AGENTS.md` titles / lead text were updated.
- Information extracted from context that did not naturally fit in `AGENTS.md`, `README.md`, or `docs/infrastructure.md`, if any.
- Recommended next actions, if additional confirmation is needed.

## Notes

- Keep the template docs structure minimal and do not add unnecessary files.
- Never edit generated files under `docs/generated/`.
- Preserve the rule that files under `docs/` are English and `README.md` is Japanese.
- Decide whether to add information by asking "would AI make a mistake if this were deleted?" If not, do not add it.
- Avoid duplicated rules. Put durable rules in `AGENTS.md` and reference information in `docs/`.
- In derived projects, the text should read as "this project documentation", not "template documentation".

---
name: project-docs-init
description: Initialize docs for a new project expanded from the template. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary documentation work.
---

# Project Docs Initialization

Initialize documentation for a new project expanded from the template repository.

## Purpose

This template is a Laravel + Inertia + Docker starter, so each derived project needs its own customized `docs/` content. Read the existing project context, such as `old_docs/`, and reflect project-specific information while preserving the template docs structure.

Important:

- Keep docs short.
- Do not add inferable generalities or long tutorials.
- Keep project context specific, stable, and not obvious from code alone.

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
| `AGENTS.md` | Adapt the project summary and session rules (Step 4). |
| `CONTEXT.md` | Fill initial entries with domain terms extracted from context. Preserve the opening contract text and entry format. |
| `docs/infrastructure.md` | Record only external production facts that differ from the template and cannot be read from repository configuration. |
| `.env.example` | Update default values for `APP_NAME`, `DB_DATABASE`, `VITE_APP_NAME`, and port numbers. |
| `public/manifest.webmanifest` | Update the static app name and description in the Web App Manifest. |
| `README.md` | Update the project name, purpose, audience, scope, and setup text. |
| `docs/adr/NNNN-*.md` | Record adopted app-specific trade-offs only when they meet `docs/adr/README.md`; update its App ADRs index. |

The following files are template-shared and should generally not be changed:

- `docs/generated/boost.md` - generated; do not edit.
- `docs/generated/specs.md` - generated; do not edit.
- `docs/testing-as-spec.md` - testing-as-spec method; shared by the template.
- `docs/adr/template/**` and the policy in `docs/adr/README.md` - template-owned; only the App ADRs index is app-owned.

## Step 4: Update Entry Documents

Adapt `README.md` and `AGENTS.md` within their existing roles:

**`README.md`**

- Change the title to the project name or project-name-based wording.
- Describe the purpose, audience, and scope briefly in the opening paragraphs.
- Do not leave scaffold-oriented explanations such as "this is a template" or "for horizontal rollout across multiple projects".
- Keep setup steps for actual users.

**`AGENTS.md`**

- Replace the summary with one sentence about the project's purpose and audience.
- Keep applicable shared rules, essential validation commands, and non-inferable session constraints.
- Omit behavior descriptions, full task catalogs, and information owned by other docs.

## Step 5: Update Local Identifiers and Port Settings

Using the user-provided `APP_NAME`, `DB_DATABASE`, port numbers, and the project name extracted from context, update:

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
  authentication.
- Enable the GitHub dependency graph and Dependabot Alerts. Leave dependency update PRs disabled.

No OPcache measurement is required to start deploying. The deployment waits out the revalidation
window while maintenance is active. Recheck the external assumption only if the environment leaves
XServer, changes PHP version, gains a `.user.ini`, or serves stale behavior after a green deploy.

## Step 6: Review and Verify

1. Present the updates to the user and get approval.
2. Run `pnpm lint:docs` to verify link consistency under `docs/`.
3. Confirm README and AGENTS follow Step 4.
4. Report unresolved context rather than forcing every source detail into docs.

## Output

After finishing, report:

- List of updated files and summary of changes.
- Essential context still unresolved or intentionally omitted, if any.
- Recommended next actions, if additional confirmation is needed.

## Notes

- Keep the template docs structure minimal and do not add unnecessary files.
- Never edit generated files under `docs/generated/`.
- Preserve the rule that files under `docs/` are English and `README.md` is Japanese.
- Decide whether to add information by asking "would AI make a mistake if this were deleted?" If not, do not add it.
- In derived projects, the text should read as "this project documentation", not "template documentation".

---
name: project-docs-init
description: Initialize docs for a new project expanded from the template. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary documentation work.
---

# Project Docs Initialization

Initialize documentation for a new project expanded from the template repository.

## Purpose

This template is a Laravel + Inertia + Docker starter, so each derived project needs its own customized `docs/` content. Read the existing project context, such as `old_docs/`, and reflect project-specific information while preserving the template docs structure.

Start this once in the primary checkout. Adapt the app's documentation and defaults before the
first `mise run setup`, then prepare the primary environment and verify in Step 6. Commit the
initialized files after verification and before creating additional Worktrees. Subsequent Orca
Worktrees use `mise run setup` without rerunning this skill.

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
2. **Local identifiers and port block**: `APP_NAME`, `DB_DATABASE`, and an unused 100-port block starting at `DEV_PORT_BASE`. These are chosen per application; Worktree setup assigns ports within that block.

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
| `.env.example` | Update default values for `APP_NAME`, `DB_DATABASE`, `VITE_APP_NAME`, and the app's port block. |
| `public/manifest.webmanifest` | Update the static app name and description in the Web App Manifest. |
| `README.md` | Build from `assets/README.md`, filling the app-owned blocks described in Step 4. |
| `docs/adr/NNNN-*.md` | Record adopted app-specific trade-offs only when they meet `docs/adr/README.md`; update its App ADRs index. |

The following files are template-shared and should generally not be changed:

- `docs/generated/boost.md` - generated; do not edit.
- `docs/generated/specs.md` - generated; do not edit.
- `docs/testing-as-spec.md` - testing-as-spec method; shared by the template.
- `docs/adr/template/**` and the policy in `docs/adr/README.md` - template-owned; only the App ADRs index is app-owned.

## Step 4: Update Entry Documents

Adapt `README.md` and `AGENTS.md` within their existing roles:

**`README.md`**

Use [the derived-app README scaffold](assets/README.md), not Harnavel's root README. Its links are
relative to the generated README at the repository root. Keep its shared text and marker pairs;
replace all placeholders inside the following app-owned blocks:

| Block | Content |
| --- | --- |
| `app:overview` | `PROJECT_NAME` and `PROJECT_OVERVIEW`: the app's title, purpose, audience, scope, features, and product usage. |
| `app:development` | `APP_DEVELOPMENT_NOTES`: app-specific prerequisites, environment values, extra commands, and links to app-specific development or operations docs. |
| `app:notices` | `APP_NOTICES`: app-specific licensing, privacy, trademark, and other notices. |

Use supplied context and any existing app README to fill these blocks, preserving app-specific
information. Empty optional blocks keep their markers with no placeholder text. Remove inherited
template promotion and one-time initialization instructions. Shared technology, Orca setup,
standard commands, and documentation links follow the scaffold. Put additional app-specific
content in the appropriate block rather than editing the shared text. Document local URLs through
`mise run dev:info`.

The scaffold is template-owned and remains unchanged in derived apps. Later template merges
update README text outside the app blocks from the latest scaffold while preserving their bodies.

**`AGENTS.md`**

- Replace the summary with one sentence about the project's purpose and audience.
- Keep applicable shared rules, essential validation commands, and non-inferable session constraints.
- Omit behavior descriptions, full task catalogs, and information owned by other docs.

## Step 5: Update Local Identifiers and Port Settings

Using the user-provided `APP_NAME`, `DB_DATABASE`, `DEV_PORT_BASE`, and the project name extracted from context, update:

- Relevant `APP_NAME`, `DB_DATABASE`, and `VITE_APP_NAME` defaults in `.env.example`.
- `DEV_PORT_BASE` and the primary-checkout port defaults in `.env.example`: Nginx at base, Vite at base + 1, MariaDB at base + 2, Mailpit SMTP at base + 3, and Mailpit UI at base + 4. Additional Worktree ports are assigned by setup; do not prescribe fixed URLs for them in README.
- `name`, `short_name`, and `description` in `public/manifest.webmanifest`.

For an app with registered development environments, retain its existing port block; changing it
requires a separate environment migration. See [Worktree development](../../../docs/worktree-development.md).

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
- Enable the GitHub dependency graph and Dependabot Alerts, leave Dependabot update PRs disabled,
  and install Mend Renovate on the repository ([Dependency updates](../../../docs/branches-and-ci.md#dependency-updates)).

The server-side `.env` of each deployed environment also needs `PASSKEYS_USER_HANDLE_SECRET` set
explicitly, independent of `APP_KEY` for a new app; production refuses to boot without it
([TADR-0013](../../../docs/adr/template/0013-passkey-credentials.md)).

No OPcache measurement is required to start deploying. The deployment waits out the revalidation
window while maintenance is active. Recheck the external assumption only if the environment leaves
XServer, changes PHP version, gains a `.user.ini`, or serves stale behavior after a green deploy.

## Step 6: Review, Set Up, and Verify

1. Present the updates to the user and get approval.
2. After the app defaults are finalized, run `mise run setup` in the primary checkout. This prepares
   the dependencies, containers, and generated assets needed by verification and commit hooks.
   Keep the `app` container running for the pre-commit backend analysis.
3. Run `pnpm lint:docs` to verify link consistency under `docs/`.
4. Confirm README and AGENTS follow Step 4: all README placeholders are replaced, each app block
   occurs once with its marker pair intact, and text outside the blocks matches the scaffold.
5. Report unresolved context rather than forcing every source detail into docs.

## Output

After finishing, report:

- List of updated files and summary of changes.
- Essential context still unresolved or intentionally omitted, if any.
- Recommended next actions, if additional confirmation is needed.

## Notes

- Keep the template docs structure minimal and do not add unnecessary files.
- Decide whether to add information by asking "would AI make a mistake if this were deleted?" If not, do not add it.
- In derived projects, the text should read as "this project documentation", not "template documentation".

---
name: project-docs-init
description: Initialize docs for a new project expanded from the template. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary documentation work.
---

# Project Docs Initialization

Initialize project-specific documentation and defaults once, in a new derived app's primary
checkout, before the first `mise run setup`; commit the result before creating additional
Worktrees, which then only run `mise run setup`. Add a fact only if an agent would err without it:
keep it specific, stable, and not obvious from code, and make the text read as this project's
documentation rather than the template's.

## Step 1: Confirm Inputs and Read Context

1. Confirm with the user the context directory (default `old_docs/`), `APP_NAME` (kebab-case),
   `DB_DATABASE` (snake_case), and an unused 100-port block starting at `DEV_PORT_BASE`
   ([Worktree development](../../../docs/worktree-development.md)). An app with registered
   development environments keeps its existing block; changing it is a separate environment
   migration.
2. Read every file in the context directory for the project's purpose, users, scope, domain terms,
   technical constraints, and open questions.

## Step 2: Update Project Files

`docs/generated/**`, `docs/testing-as-spec.md`, `docs/adr/template/**`, the policy in
`docs/adr/README.md`, and the README scaffold itself stay as the template ships them. Update:

- **`README.md`**: build from [the derived-app README scaffold](assets/README.md), not Harnavel's
  root README; its links are relative to the repository root. Keep all text outside the marker
  pairs and fill the app-owned blocks from the context and any existing app README, putting further
  app content in a block rather than in shared text:
  - `app:overview`: `# <Project Name>`, purpose, audience, scope, features, and usage.
  - `app:development`: app-specific prerequisites, environment values, extra commands, and
    development or operations doc links. Local URLs come from `mise run dev:info`.
  - `app:notices`: licensing, privacy, trademark, and other notices.
  An empty block keeps its markers with no placeholder text.
- **`AGENTS.md`**: replace the summary with one sentence on the project's purpose and audience; keep
  the applicable shared rules, essential validation commands, and non-inferable session
  constraints, and leave behavior and task catalogs to code and other docs.
- **`GLOSSARY.md`**: add initial domain terms, keeping its header and entry format.
- **`docs/infrastructure.md`**: record only external production facts that differ from the template.
- **`docs/adr/NNNN-*.md`**: add App ADRs only for adopted app-specific trade-offs meeting
  `docs/adr/README.md`, listed under its `## App ADRs`.
- **`.env.example`**: `APP_NAME`, `DB_DATABASE`, `VITE_APP_NAME`, `DEV_PORT_BASE`, and the
  primary-checkout ports: Nginx at base, Vite at base + 1, MariaDB at base + 2, Mailpit SMTP at
  base + 3, and Mailpit UI at base + 4.
- **`public/manifest.webmanifest`**: `name`, `short_name`, and `description`.

## Step 3: Set Up, Verify, and Report

1. Present the edits for approval.
2. Run `mise run setup` in the primary checkout and keep the `app` container running, because the
   pre-commit hook analyzes PHP inside it. Run `pnpm lint:docs`, and confirm every README
   placeholder is replaced, each app block occurs once with its markers, and text outside the
   blocks matches the scaffold.
3. Tell the user which settings outside the repository the first deployment needs, for Develop and
   Production separately:
   - GitHub environment secrets `XSERVER_*` and `DEPLOY_DIRECTORY`, `HEALTHCHECK_URL` (the canonical
     HTTPS URL of the Laravel health route, such as `https://example.com/up`), and
     `HEALTHCHECK_BASIC_AUTH` only where Basic authentication protects the document root.
   - The dependency graph and Dependabot Alerts enabled with Dependabot update pull requests left
     disabled, and Mend Renovate installed
     ([Dependency updates](../../../docs/branches-and-ci.md#dependency-updates)).
   - The server-side settings in [External infrastructure state](../../../docs/infrastructure.md)
     and an explicit `PASSKEYS_USER_HANDLE_SECRET`, independent of `APP_KEY`
     ([TADR-0013](../../../docs/adr/template/0013-passkey-credentials.md)).
4. Report the updated files and any context left unresolved or intentionally omitted, rather than
   forcing every source detail into docs.

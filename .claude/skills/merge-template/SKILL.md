---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [BRANCH=main]"
---

# Merge Template

Resolve conflicts from `git pull <remote> <branch>` using the repository rules for app-specific content versus template-aligned content.

## Resolution Policy

Use [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md) for the classification model, the per-file rules below to classify a concrete path, and the Path Exceptions section when a path resists those rules.

- **Prefer the template side**: template-origin hunks for common foundations such as auth, mail, and settings; scaffolding; test helpers; naming and coding conventions; library updates; generic UI primitive scaffolds; and testing-as-spec files (`docs/testing-as-spec.md`, template ADRs under `docs/adr/template/**`, architecture test scaffolds, `reusable-test.yml` / `mutation.yml` / `.github/actions/**`).
- **Prefer the app side**: domain logic, custom UI components / design tokens, custom ViewModel / Service / Repository patterns, i18n and multilingual tests, app-specific routes/controllers, numbered App ADRs (`docs/adr/NNNN-*.md`, flat namespace), and `CONTEXT.md` (domain glossary; the template only provides the seed and the app owns it).
- **Hybrid (`AGENTS.md`)**: keep template-aligned structure and operational rules such as Verification / Testing / Documentation, while preserving app-specific Project Context. Agent entry files (`CLAUDE.md` / `GEMINI.md`) stay one-line pointers aligned with the template.
- **Hybrid (CI entry workflows)**: for `.github/workflows/check.yml` / `.github/workflows/quality.yml`, keep the shared `ci-lint` call structure aligned with the template and preserve app-specific steps such as additional frontend unit tests. For `.github/workflows/delivery.yml`, keep the shared deploy structure, ssh/retry hardening, Laravel internal excludes, in-flight drain step, and fail-closed release health verification / withdrawal steps (the URL lives in each environment's `HEALTHCHECK_URL` secret) aligned with the template, while preserving sibling-subdomain / server-layout `--exclude` values, the release-critical asset list, app-specific extra steps, and the drain duration with its adjacent app rationale.
- **Hybrid (`docs/infrastructure.md`)**: keep the shared XServer toolchain and deployment availability/recovery runbook aligned with the template, while preserving app-specific local ports, scheduled jobs, paths, and operational procedures.
- **Hybrid (`docs/adr/README.md`)**: keep the ADR policy body aligned with the template and preserve the app-owned ADR index section such as `## App ADRs`.
- **Hybrid (`mise.toml`)**: first consider promoting new tasks to the template. Allow app-side additions only for unavoidable project-specific workflows. When pulling from the template, avoid touching this file unless the shared command surface must change; if it must, review it together with `AGENTS.md` / `docs/commands.md`.
- **Hybrid (`phpstan.neon.dist`)**: keep `parseModelCastsMethod: true` and its reason comment aligned with the template ([`TADR-0007`](../../../docs/adr/template/0007-inference-first-model-types.md)); analysis paths, ignore rules, and other calibration stay app-side.
- **Calibration values (app-priority exception)**: coverage / mutation gate values (`--min` numbers), whether mutation is blocking (`continue-on-error`), and the immediately adjacent reason comments. `reusable-test.yml` / `mutation.yml` themselves are template-aligned, but these lines are calibrated per project and should keep the app-side values ([`TADR-0001`](../../../docs/adr/template/0001-development-workflow.md): each project's CI workflow is the single canonical source for gate values; [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md): shared-surface convergence with calibration carve-outs).
- **Tests**: if both sides cover the same behavior, merge them or delete one side as appropriate. When aligning, prefer the template side. Preserve app-specific coverage such as i18n tests.
- **ADR namespace**: after the pull, follow `docs/adr/README.md` for cleanup such as deleting old template ADRs, retargeting references to `TADR-0001`, and compacting App ADR numbers.

## Path Exceptions

The rules above decide most paths. Listed here are only the cases where the obvious reading gives
the wrong answer. This list is deliberately not exhaustive: an unlisted path is classified by the
rules above, not by its absence here. [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md)
rejects an exhaustive manifest, because it turns every new file into a second place to update.

- **Template-owned inside an otherwise app-owned tree**: `app/Concerns/PasswordValidationRules.php`.
  Fully template-owned; do not read this as making its whole directory template-owned.
- **Deleted upstream**: `app/Http/Controllers/ReleaseHealthController.php`, `app/Support/ReleaseIdentity.php`,
  `app/Support/OpcacheRevalidation.php`, `app/Support/ReleaseVerification.php`, and
  `tests/Feature/Deployment/ReleaseIdentityTest.php` implemented the release identity gate the
  template removed after measuring XServer's OPcache revalidation
  ([`TADR-0006`](../../../docs/adr/template/0006-planned-maintenance-deployments.md)). Take the
  deletion: do not resurrect them, and delete any `RELEASE_IDENTITY_ENFORCEMENT` variable left on an
  environment. Verification is now two probes, so an app carrying a `/up/release` route or a
  release-identity literal in `public/index.php` drops both in the same merge.
  **Delete this entry once every derived application has taken the deletion** — it exists only to
  stop a merge from restoring files the template no longer has, and becomes archaeology after that.
- **Hybrid even though the tree suggests one side owns it**: `public/index.php` (the Laravel
  bootstrap is template-origin; app additions stay), and
  `tests/Feature/Architecture/ArchTest.php` (template rules converge, app rules accumulate — the
  rest of `tests/Feature/Architecture/**` is template-owned). `app/Http/Controllers/Settings/**` and
  `app/Http/Controllers/Auth/GoogleAuthController.php` are likewise Hybrid, not app-owned, even
  though `app/Http/Controllers/**` generally is: they carry the shared auth and settings
  foundations from the Resolution Policy above.
- **App-owned inside a template-owned tree**: App ADRs (`docs/adr/NNNN-*.md`) and the `## App ADRs`
  section of `docs/adr/README.md`.
- **Lockfiles are app-owned**: `composer.lock` and `pnpm-lock.yaml` hold the app's own dependency
  set, so never resolve a conflict by taking the template's copy — that silently drops app
  dependencies or breaks the next frozen install. The "prefer the template side for library
  updates" rule applies to the manifests (`composer.json`, `package.json`), not to the lockfiles.
  After merging the manifests, regenerate the lockfiles with the package managers and verify with a
  clean install rather than merging them by hand.
- **Calibration lines (app-priority)**: `--min` and `continue-on-error` with their immediately
  adjacent reason comments in `reusable-test.yml` / `mutation.yml`; and the `Drain in-flight
  requests` duration with its immediately adjacent app rationale. The steps that read these values
  are shared.
- **Mutation selection is a migration, not a merge**: the template selects with `mutates()` in the
  specs, an app selects with `--class`. Keep the app's `--class`, `--min`, and
  `@pest-mutate-ignore` annotations until it has added `mutates()` to the specs of its own
  deterministic classes and re-measured; taking `mutation.yml` first leaves the gate green on the
  two shared ViewModels while every app-specific class silently stops being tested
  ([`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md)).
- **`E2E_PORT` must be set in `.env` before E2E runs** (convention: `NGINX_PORT + 1000`).
  `compose.yaml` falls back to `9080` so unrelated `docker compose` commands keep working;
  `playwright.config.ts` has no default, so a second application cannot silently test against the
  first one's server. `APP_URL` is derived from it, so `.env.e2e` must not pin one.
- **Not in any file**: The optional `HEALTHCHECK_BASIC_AUTH` environment secret is app-specific in
  the same way. An environment whose document root is protected by Basic authentication (a develop
  host behind `.htaccess.develop`, for example) needs it so both verification probes authenticate;
  an unauthenticated probe returns 401 and withdraws a healthy release. Converge the workflow's
  shared wiring for it, but never copy the credential between repositories or set it on an
  environment without such protection.

## Arguments

- `REMOTE`: Remote name to merge from. Default: `template`.
- `BRANCH`: Branch name to merge from. Default: `main`.

## Workflow

### 1. Preflight

```bash
git status
git remote -v
```

- Check whether a merge is in progress (`MERGE_HEAD` exists).
- Check whether `git pull <REMOTE> <BRANCH>` has already run.
- If not, run it. When it fails with conflicts, continue with this workflow.

### 2. List Conflicts and Track Tasks

```bash
git diff --diff-filter=U --name-only
```

- Get the conflict list and create file-level tasks.
- If there are many files, group related files such as auth or settings.

### 3. Resolve Each Conflict

Open each file and resolve every `<<<<<<< HEAD / ======= / >>>>>>> ...` block.

Decision procedure:

1. Understand the intent of both sides by reading docs, nearby code, and existing tests.
2. Classify the change as app-specific, template-aligned, or hybrid.
3. For hybrid changes, preserve app-specific conventions such as dedicated ViewModel patterns or custom Repository methods while adopting the template implementation direction where appropriate.
4. For PII or security-related changes, choose the best-practice side, such as removing email from logs.
5. After resolving, confirm no conflict markers remain.

```bash
git grep -nI -E '^(<<<<<<<|=======|>>>>>>>)' -- . \
  ':(exclude)vendor/**' \
  ':(exclude)node_modules/**' \
  ':(exclude)public/build/**' \
  ':(exclude)storage/**' \
  ':(exclude)bootstrap/cache/**' \
  ':(exclude)docs/generated/**'
```

### 4. Clean Up App-Specific Methods or Dead Code

- Remove methods that became unused after aligning with the template, such as custom Repository methods.
- Clean up related test fakes and mocks at the same time.

### 5. Verify

Run commands according to the project rules. Use the Verification section in `CLAUDE.md` / `AGENTS.md`.

Examples for Laravel + Inertia/React apps:

- Backend: `mise run verify:backend`, or `docker compose exec app ...` when narrower checks are appropriate.
- Frontend: `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`, `pnpm test:e2e --reporter=dot`.
- Docs: `pnpm lint:docs`.

If format check reports warnings, run `pnpm format` and then rerun the check.

### 6. Stage and Summarize

```bash
git add <resolved-files>
git status
git diff --cached --stat
```

- Confirm that all conflicts are resolved and Git reports `All conflicts fixed`.
- Do not commit. The user reviews and commits.

### 7. Report

Report the following to the user:

- Template-side choices: files and brief reasons.
- App-side choices: files and brief reasons.
- Test consolidation: deleted, merged, and preserved app-specific tests.
- Verification results: pass status for each command.
- Upstream candidates: if conflict resolution exposed a template-side bug or improvement, include a ready-to-file issue or PR note.

## Important Principles

- **Understand before deciding**: compare both sides' intent against surrounding code. Avoid superficial text merges.
- **Keep defense in depth**: do not remove security double checks merely because they look redundant.
- **Do not keep PII**: prefer changes that remove personal information such as email addresses from logs.
- **Watch implicit dependencies**: if behavior relies on settings such as DB collation, confirm the dependency is explicit in project policy.
- **Balance DRY and explicitness**: follow app conventions when they exist, for example replacing the template's inline implementation with a dedicated ViewModel in apps that use that pattern.
- **Keep one environment guard block**: avoid creating multiple test-only blocks such as `if (app()->environment(['local', 'testing']))`.
- **Stop before commit**: after resolving conflicts, stop at staging and wait for user review.

## If an App-Specific Change Is a Template Bug Fix

If the diff fixes a template-side bug:

1. Judge whether the change is valid and not app-specific.
2. Keep the fix in the app and provide an upstream report draft for the template.
3. Include summary, reproduction conditions, cause, proposed fix code, and regression test.

## Examples

```bash
# Standard template remote and main branch.
/merge-template

# Custom remote or branch.
/merge-template REMOTE=upstream BRANCH=develop
```

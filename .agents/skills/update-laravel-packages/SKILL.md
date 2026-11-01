---
name: update-laravel-packages
description: Update this Laravel template's Composer, Laravel Installer, Node, pnpm, PHP/JavaScript dependencies, lockfiles, Boost resources, and application skeleton from a freshly generated Laravel reference project. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary Composer, pnpm, Laravel, dependency, or package-update work.
---

# Update Laravel Packages

Use a fresh Laravel application as the upstream baseline. Preserve deliberate template behavior,
remove obsolete dependencies inherited from older starters, and update template-only dependencies
independently. Reference manifest constraints are Laravel's compatibility baseline, not versions to
maximize.

## Required Context

Before changing files, read `AGENTS.md`, `GLOSSARY.md`, `docs/adr/README.md`, the relevant Template
ADRs, [the provenance and sync policy](references/provenance-and-sync-policy.md), Laravel's upgrade
guide for every crossed major version, and the official release notes of any template-owned
dependency whose major version changes. Inspect `git status`, manifests, lockfiles, runtime
definitions, verification tasks, deployment constraints, and their history. Behavior changes follow
the repository's test-first rules.

## Safety Gates

- Template source only: proceed only when `git remote get-url origin` is this template repository
  and `git remote get-url template` fails. Otherwise stop: derived apps take these updates through
  `git pull template main` + `/merge-template`, which reconciles their app-owned lockfiles.
- Start from a clean worktree unless the user has explicitly scoped and protected other changes.
- Docker `app` has no Node, so the Laravel Installer runs only in the throwaway `package-reference`
  build stage.
- Keep Docker and CI PHP pins unchanged unless the user requests a PHP change and confirms
  availability on every XServer target; then update them together.
- Never delete `composer.lock` or `pnpm-lock.yaml`; update them through their package managers.
- A dependency absent from the fresh application is not thereby template-owned; prove provenance.
- Stop for approval before an unexplained dependency or file removal, an audit fix requiring an
  unplanned major change, a behavior change, or either commit. Reuse approvals already given for
  the same scope in this conversation.
- Keep the reference project until both phases and the final comparison are complete.

## Phase 1: Toolchain and Dependencies

### 1. Record the baseline

Record the starting commit and status; effective PHP, Composer, Installer, Node, and pnpm versions;
the Docker PHP image tag with its XServer evidence; Laravel and major package versions; baseline
audit and narrow verification results; and current scripts and lockfile state. A pre-existing audit
failure is not this update's regression, but report it.

### 2. Update the toolchain

- Composer is a three-surface change: the image tag in `.docker/php/Dockerfile`, the
  `~/opt/bin/composer-<version>` PHAR provisioned manually on every XServer environment
  (`docs/infrastructure.md`), and every path in `.github/workflows/delivery.yml`. Update the
  Dockerfile and workflow together only after the user confirms the PHAR exists on every target.
- Keep the Dockerfile's unpinned `composer global require laravel/installer` so a no-cache rebuild
  installs the latest stable Installer.
- Pin the latest Node LTS release and stable pnpm in `mise.toml`. Treat a Node or pnpm major as a
  breaking change: read its migration guidance for lockfile, store, engine, and build-script
  impact, recreate `node_modules` when required (keeping the lockfile), and decide build scripts in
  `pnpm-workspace.yaml` (`allowBuilds`) so install succeeds and the `prepare` hook still runs; a
  blanket `ignore-scripts` breaks that hook. Report the resulting derived-app steps.
- Keep `require.php` aligned with the Docker/XServer series CI exercises; the reference's broader
  floor is not this template's promise. Raising the minimum needs approval.
- Rebuild without cache, start the containers, and stop unless the expected versions are active.

### 3. Generate the reference application

Generate the reference inside the `package-reference` container's temporary filesystem, never in
the repository or the running `app` container:

```sh
docker build --target package-reference -t laravel-package-reference ./.docker/php
docker run --rm -it laravel-package-reference bash
```

- Inspect `laravel new --help` first; prompts and flags are versioned interfaces.
- Select the options this template uses: React, TypeScript, Inertia, Pest, Codex, Claude Code,
  authentication, database, and other features already present. Never enable SSR or a queue-worker
  requirement.
- Run without `--no-node`: the starter's post-create feature selection (for example
  `install:features`) shells out to a package manager, and without one it aborts and leaves a
  misleading pre-selection reference (PHPUnit instead of Pest, Boost and Wayfinder unapplied).
- Trust the reference only after confirming the feature-selection scaffold is gone, Pest and Boost
  are installed, and Wayfinder output exists.
- Copy it to a host temporary directory, record the installer version, command, and answers, and
  remove the image when the run ends. Exclude only the comparison noise the policy lists.

### 4. Classify every manifest difference

Before editing either manifest, build a package disposition table: package, section, current and
reference constraints, provenance (per the policy), action, evidence, and related files. Establish
ownership from retained consumers and history; presence in the reference does not override
deliberate template ownership.

- Template-owned: the latest compatible stable release becomes the verified minimum; keep an older
  major only for a retained consumer.
- Adopted Laravel-owned: use the reference constraint string, even with an older lower bound,
  unless retained behavior requires a recorded compatibility exception. Choose the section by the
  retained consumer's role (build, type, and test tools in development dependencies).
- Remove inherited dependencies without retained consumers; migrate a still-used legacy package to
  Laravel's replacement without changing behavior first.
- Unclear provenance or replacement: leave it and ask with the evidence.

Classify non-dependency fields too (scripts, autoloading, plugins, metadata, package-manager
settings, hooks, empty values, consumed installer markers). Keep canonical commands; do not restore
aliases history removed; drop scripts unusable across the host/container and XServer boundaries
with their dedicated dependencies. The frontend harness is template-owned (Oxfmt formats,
type-aware Oxlint is the only linter, TypeScript type-checks); a generated reference never brings
back Prettier or ESLint. Reassess explicit tool settings and overrides against current defaults and
parent requirements, removing equivalents without weakening diagnostics.

Trace every package new to the reference through its usages and companion files. Adopt a capability
whole (components, hooks, bootstrap wiring, flash handling, configuration, tests, styles) or omit
the package and its companions together; never leave it orphaned. Default frontend components are
not adopted merely because the starter gained them. Inert infrastructure may be adopted while
existing emitters and feedback stay; companion code that would replace an existing flow needs
approval explaining how the behavior and its tests reach derived apps.

Required APIs, bug fixes, and security fixes must reach derived apps that keep compatible locked
versions, so express them as shared minimums or exclusions per
[the policy](references/provenance-and-sync-policy.md#shared-dependency-compatibility). Before
resolution, include a machine-generated comparison of Laravel-owned constraints and sections against
the reference; no unexplained mismatch may remain.

This skill owns every shared constraint string. Renovate merges only lockfile updates within them
and holds declaration changes for approval, except that a vulnerability fix may move a constraint to
the lowest fixed stable version; major and 0.x fixes still wait for review.

### 5. Resolve and audit

1. Snapshot the approved manifests and resolution settings, then resolve without rewriting declared
   ranges: container `composer update --no-interaction --no-scripts` (keeps Boost generation out of
   resolution) and host `pnpm update --no-save --lockfile-only`.
2. Confirm the lockfile still records each aliased or overridden importer's resolved specifier
   ([the `vite@*` symptom and fix](../merge-template/references/dependency-reconciliation.md#routine-app-side-maintenance));
   never repair it by editing the lockfile.
3. Review direct and transitive changes, then run `composer audit --locked` and `pnpm audit`.
   Resolve advisories within the reference constraints where possible; a fix needing a
   Laravel-owned constraint change takes the smallest compatible change as an approved exception.
   Raising reference constraints to registry latest is not an audit fix. Accepted advisories and
   abandoned packages need approval; report development-only findings left open.
4. Compare manifests and settings with the snapshot, investigate unexpected writes, and search code,
   configuration, scripts, CI, tests, and docs for every removed package and command.
5. Delete `vendor` and `node_modules`, then run container `composer install --no-interaction` and
   host `pnpm install --frozen-lockfile`.

### 6. Refresh generated guidance and verify

- Run `docker compose exec -T app php artisan boost:update` after the clean install; add
  `--discover` only when new packages may ship guidance, and review interactive additions.
- Run the affected narrow checks, then `mise run verify:backend`, `pnpm format:check`,
  `pnpm lint:check`, `pnpm types:check`, `pnpm test:unit`, `pnpm lint:docs`, and
  `pnpm test:e2e --reporter=dot`.
- After approval, commit the toolchain, manifests, lockfiles, and Boost resources alone, without
  skeleton synchronization.

## Phase 2: Laravel Skeleton Synchronization

1. Compare the reference and repository recursively, including deletions, renames, and mode changes,
   and build a file disposition table classifying each difference as upstream-following,
   template-preserved, or comparison noise. Absence from the new reference is a meaningful deletion,
   not noise.
2. Adopt current Laravel code, configuration, and syntax in upstream-following files, applying the
   repository's comment policy to upstream text too. Default frontend pages, components, layouts,
   hooks, and presentation stay template-preserved unless the template reuses that surface or the
   user approves. Remove upstream files current Laravel deleted when nothing retained uses them.
3. In template-preserved files, port compatible upstream syntax, formatting, and path conventions
   line by line around the preserved behavior, even where the template is a stricter superset.
4. Shipped migrations stay unchanged; an upstream schema change becomes a new alter migration,
   verified with fresh migrate and rollback, only for tables the template still has.
5. Add `.gitignore` entries, generated-artifact ignores, or config only for features the template
   uses. Re-run Wayfinder generation if routes or controllers change.
6. Re-run the full Phase 1 verification, plus the audits if dependency files changed, search again
   for removed names, repeat the full reference comparison until every difference is in the table,
   review `git diff --check` and the full diff, delete both reference directories, and commit only
   after approval.
7. Touch ADRs or `GLOSSARY.md` only when a durable decision or domain term changed; an upgrade
   alone is not an ADR.

## Report

Keep run evidence in the conversation or temporary artifacts, never in repository docs. Summarize:
baseline and reference options; toolchain changes; package and non-dependency disposition tables;
the machine-generated constraint comparison with exceptions; audit findings; the file disposition
table; verification results; derived-app actions beyond routine lockfile refreshes; and blockers,
approvals, and both commit IDs. The update is complete only when clean installs, required checks,
and the classified comparison pass, and audits pass or carry documented exceptions.

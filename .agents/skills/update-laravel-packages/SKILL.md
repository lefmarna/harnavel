---
name: update-laravel-packages
description: Update this Laravel template's Composer, Laravel Installer, Node, pnpm, PHP/JavaScript dependencies, lockfiles, Boost resources, and application skeleton from a freshly generated Laravel reference project. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary Composer, pnpm, Laravel, dependency, or package-update work.
---

# Update Laravel Packages

Use a fresh Laravel application as the upstream baseline. Preserve deliberate template behavior,
remove obsolete dependencies inherited from older Laravel starters, and update deliberate
template-only dependencies independently. Treat reference manifest constraints as Laravel's
compatibility baseline, not as versions to maximize.

## Required Context

Before changing files:

- Read `AGENTS.md`, `CONTEXT.md`, `docs/adr/README.md`, all relevant Template ADRs, and
  `references/provenance-and-sync-policy.md` in this skill.
- Read Laravel's official upgrade guide for every crossed Laravel major version.
- Read the official release or compatibility information for template-owned dependencies before
  changing their major versions.
- Inspect `git status`, the current manifests, lockfiles, runtime definitions, verification tasks,
  deployment constraints, and their git history.
- Treat tests as the behavior specification. If migration work changes behavior, follow the
  repository's test-first rules before implementing it.

## Safety Gates

- Run this skill only in the template source repository. Before any update, inspect both
  `git remote get-url origin` and `git remote get-url template`. Proceed only when `origin`
  identifies this template source repository and no `template` remote indicates a derived app. If
  a `template` remote exists, `origin` identifies another repository, or ownership cannot be
  established, stop and direct the work to the template repository. A derived app synchronizing
  its skeleton against a fresh Laravel application would fight template convergence; derived apps
  take these updates through `git pull template main` + `/merge-template`, which reconciles their
  app-owned lockfiles according to its
  [dependency policy](../merge-template/references/dependency-reconciliation.md). Routine package
  maintenance and unrelated security fixes are separate app work.
- Require a clean worktree before starting. If unrelated changes exist, stop unless the user has
  explicitly scoped and protected them.
- Run PHP, Composer, and Artisan only inside the Docker `app` container, and the template's Node,
  pnpm, and mise on the host. The Laravel Installer is the exception: the `app` image carries no
  Node, so reference generation (Phase 1 Step 3) runs it in the throwaway `package-reference` build
  stage, which adds Node for that one task and is discarded afterwards.
- Do not change the Docker PHP image tag in this workflow, including patch updates. It is an exact
  production-compatibility pin chosen for XServer, not a toolchain version to make latest. A
  separately requested PHP change requires confirming XServer support first.
- Never deploy, run production migrations, modify production data, or start unsupported
  long-running production processes.
- Never delete `composer.lock` or `pnpm-lock.yaml`. Update them through their package managers and
  verify them with clean installs.
- Do not infer that a dependency is template-owned merely because it is absent from the fresh
  application. Prove its provenance first.
- Stop for approval before an unexplained dependency or file removal, an audit fix requiring an
  unplanned major change, a behavior change, or either commit.
- Keep the reference project until both phases and the final comparison are complete.

## Phase 1: Toolchain and Dependencies

### 1. Record the baseline

Record in the execution report:

- the starting commit and worktree status;
- effective PHP, Composer, Laravel Installer, Node, and pnpm versions;
- the exact Docker PHP image tag and the XServer compatibility evidence for keeping it fixed;
- Laravel and major ecosystem package versions;
- baseline audit and narrow verification results;
- current `composer.json` / `package.json` scripts and lockfile state.

Do not treat an existing audit failure as introduced by this update, but do not silently preserve
it.

### 2. Update the toolchain

- Resolve the latest stable Composer image tag from official Composer sources. A Composer update
  is a three-surface change: the image tag in `.docker/php/Dockerfile`, the versioned
  `~/opt/bin/composer-<version>` PHAR provisioned manually on every XServer environment, and the
  paths in the `.github/workflows/delivery.yml` preflight and post-deploy commands (see
  `docs/infrastructure.md`, XServer toolchain). Update the Dockerfile and every workflow
  occurrence together —
  `pnpm test:toolchain-versions` enforces their agreement — and stop for approval until the user
  confirms the new PHAR exists on every deploy target; merging without it breaks the next deploy
  at the preflight. The preflight executes the exact PHAR and checks its version before maintenance
  mode or `rsync --delete`, but it does not provision the binary.
- Keep the Dockerfile's unpinned `composer global require laravel/installer` behavior so a no-cache
  rebuild installs the latest stable Laravel Installer. If it becomes pinned, update it explicitly.
- Resolve the latest stable Node and pnpm versions using their official registries or mise and
  update `mise.toml`.
- Treat a Node or pnpm major-version bump as a breaking toolchain change, not a silent number
  update. Read the affected versions' official migration guidance for lockfile, store, engine, and
  build-script compatibility; do not assume every pnpm major requires a new lockfile or store.
  Recreate `node_modules` when the migration requires it, preserving the lockfile, and resolve any
  dependency build-script decisions explicitly in `pnpm-workspace.yaml` (`allowBuilds`) so install
  succeeds while `prepare`/lefthook still runs. A blanket `ignore-scripts` workaround conflicts
  with this template's `prepare` hook. Record the resulting derived-app steps in the report.
- Keep the Docker PHP image tag byte-for-byte unchanged. Composer, Laravel Installer, Node, and
  pnpm being latest never authorizes a PHP patch, minor, or major update. Treat Composer's
  `require.php` constraint separately as a manifest constraint, and check the reference's own
  constraint against the reference's own lockfile before copying it. A starter can declare a floor
  its locked dependencies cannot install on, and copying that constraint documents a runtime nothing
  can run. Adopt the reference only when it is self-consistent and the fixed XServer-compatible
  runtime satisfies it; otherwise declare the floor this repository pins and tests, recording the
  divergence. Stop for approval before raising the minimum.
- Rebuild without cache, start the containers, and verify the effective versions. Stop if the
  expected versions are not active.

### 3. Generate the reference application

- Inspect `laravel new --help` first; installer prompts and flags are versioned interfaces.
- Build the throwaway `package-reference` stage and generate the reference inside *that*
  container's temporary filesystem — never in the repository and never in the running `app`
  container:

  ```sh
  docker build --target package-reference -t laravel-package-reference ./.docker/php
  docker run --rm -it laravel-package-reference bash
  ```

  Remove the image (`docker image rm laravel-package-reference`) when the run is finished.
- Select the options matching this template: React, TypeScript, Inertia, Pest, Codex, Claude Code,
  authentication, database, and other features already present in the project.
- Do not enable SSR or a production queue-worker requirement. XServer has no long-running process
  support.
- Current starter kits run a post-create feature-selection step (e.g. Laravel Chisel's
  `install:features`) that shells out to a JS package manager. That step is the only reason this
  task needs Node beside PHP, and it is why the Node lives in the `package-reference` stage rather
  than in the development `app` image (`compose.yaml` pins `target: app`). The stage already runs
  `corepack enable`, so run the generator without `--no-node`. With `--no-node`, or with no package
  manager available, the step aborts and leaves a misleading pre-selection reference (PHPUnit
  instead of Pest, Boost and Wayfinder unapplied, scaffolding not chiseled).
- Verify the reference actually completed before trusting it: the feature-selection scaffold files
  are gone, the selected test framework and Boost are installed, and Wayfinder output exists.
- Copy the completed reference to a host temporary directory for comparison. Record the exact
  installer version, command, prompt answers, and selected options.
- Remove or exclude only runtime/generated comparison noise such as `.git`, `.env`, `vendor`,
  `node_modules`, build output, caches, logs, and the generated SQLite database. Keep
  `.env.example`, lockfiles, agent files, configuration, comments, and dotfiles visible.

### 4. Classify every manifest difference

Build a package disposition table before editing either manifest. For every current and reference
dependency, record package, manifest section, current constraint, reference constraint,
provenance, intended action, evidence, compatibility notes, and related files.

Apply these rules in order:

1. Trace the reference package to its consumers before adopting it. If every consumer belongs to
   frontend pages, components, layouts, styling, or another reference surface that this template
   intentionally replaces rather than reuses, classify it as unadopted reference-surface and omit
   both the package and its companion files. Presence in a fresh Laravel application alone is not
   sufficient evidence that the template needs the package.
2. Otherwise, if the package exists in the reference, treat it as current Laravel-owned and copy the
   reference constraint string and manifest section exactly. Do this even when the repository's
   current constraint has a numerically newer lower bound. Do not preserve or substitute a newer
   registry constraint merely because this is an update workflow; the reference constraint is the
   intended Laravel compatibility baseline, while the lockfile may still resolve a newer version
   allowed by that constraint.
3. If it is absent from the reference and history proves this template added it for a retained
   capability, treat it as template-owned and update it to the latest compatible stable release.
4. If it is absent from the reference and history proves an older Laravel starter supplied it,
   treat it as legacy Laravel-owned. Remove it when no retained code, config, script, CI, or docs
   require it.
5. If retained behavior still uses a legacy package, identify Laravel's current replacement and
   migrate without changing behavior before removing the package.
6. If provenance or safe replacement is unclear, do not change it; ask for approval with the
   evidence gathered.

Compare the complete manifests, including scripts, autoloading, Composer plugins, metadata,
optional dependencies, package-manager settings, and lifecycle hooks. Do not restore scripts that
history shows were intentionally removed. Adopt useful new upstream scripts only when they fit the
Docker and XServer constraints.

Build a separate disposition for every non-dependency manifest difference, including empty arrays
and objects. Do not silently discard a field because it appears to be a no-op. Determine whether it
is active configuration, a post-generation marker left after an installer hook was consumed, or an
intentional template divergence. Prefer the completed reference form when it is harmless and
records the current post-generation state; omit it only with explicit evidence and a recorded
disposition.

For scripts and lifecycle hooks, classify every key and every command as adopted, template
equivalent, or rejected with a constraint. Do not leave a partially merged scripts object with
unexplained omissions. When the template already has an equivalent canonical command used by mise,
CI, or docs, preserve that command; add a compatible upstream alias for it only after the manifest's
git history shows this repository never removed that key. The reference carrying the alias is not
evidence, and convergence is not a reason to reinstate a script a later commit deleted.
Treat the frontend quality harness as template-owned: Oxfmt owns formatting, type-aware Oxlint is
the only linter, TypeScript owns compilation checks, and tests own behavior. Do not reintroduce
Prettier or ESLint from a generated reference. Change this ownership only with current tool-behavior
evidence.
Adopt new quality checks into aggregate test scripts when they match repository gates. Reject
setup, development, SSR, queue, or package-manager commands that violate the host/container or
XServer boundaries, and record the exact conflict.

For every package newly present in the reference, trace all reference usages and companion files.
When its capability is adopted, absorb the package together with the components, hooks, bootstrap
wiring, flash-data handling, configuration, tests, and styles that make it useful. When the
template intentionally replaces that surface, omit the package and companion files together and
record the evidence. Do not add default frontend components or their dedicated dependencies merely
because the fresh starter gained them; this template's frontend is not a byte-for-byte Laravel
starter surface. Do not leave a new dependency orphaned or defer its integration without recording
that disposition.

Do not change existing observable behavior merely to exercise a newly added upstream package. It
is acceptable to adopt inert infrastructure such as a mounted provider or event listener while
preserving the template's current emitters and user feedback. If the reference companion code
would replace an existing UX or application flow, classify that integration separately, preserve
the current behavior by default, and obtain explicit approval before changing it. The approval
request must explain how the behavior and its tests will flow to derived applications through
template convergence; do not leave that downstream decision to each app implicitly.

Apply the shared dependency compatibility rules in
[the provenance policy](references/provenance-and-sync-policy.md#shared-dependency-compatibility).
Required APIs, bug fixes, and security fixes must reach derived apps that preserve compatible
locked versions. Record their minimum versions or excluded versions in the shared inputs, and
document necessary targeted migrations, including fixes delivered by lockfile-only changes.

Before lockfile resolution, run a manifest-convergence gate that compares every adopted current
Laravel-owned package's section and constraint string with the reference. Separately list every
unadopted reference-surface package and the omitted consumer surface. The mismatch list must be
empty except for individually documented, approved compatibility exceptions. Include this
machine-generated comparison in the report; a prose disposition table alone is insufficient.

In this template source repository, this skill owns every shared constraint string, Composer and
pnpm alike. Routine security patches and lockfile refreshes resolve within approved constraints;
shared constraint changes are classified here against a fresh Laravel reference. A derived app
reproduces those strings verbatim and maintains only the constraints of packages the template does
not declare, so its routine maintenance updates resolutions rather than declarations; see
[routine app-side maintenance](../merge-template/references/dependency-reconciliation.md#routine-app-side-maintenance).
Changes to shared requirements return through the template. Renovate merges only lockfile updates within
these constraints by itself and holds every declaration change for approval. Its vulnerability pull
requests are the one standing exception: moving a constraint to the lowest fixed stable version
needs no further approval, while major and 0.x fixes still wait for review.

### 5. Resolve and audit

- Edit the manifests only after the disposition table is complete, then preserve the approved
  manifests and resolution settings for comparison after resolution.
- Generate the Composer lockfile with `composer update --no-interaction --no-scripts` inside the
  container. Delaying scripts keeps Boost generation out of the dependency-resolution step.
- Generate the pnpm lockfile with host `pnpm update --no-save --lockfile-only`. This template
  maintenance workflow intentionally refreshes the dependency graph; preserve the approved
  declarations instead of letting pnpm rewrite their ranges.
- Confirm the regenerated lockfile still records each aliased or overridden importer's resolved
  specifier; the pnpm `vite@*` override symptom and its fix are in
  [routine app-side maintenance](../merge-template/references/dependency-reconciliation.md#routine-app-side-maintenance).
  Never repair the mismatch by editing the lockfile.
- Review direct and transitive changes before continuing.
- Run `composer audit --locked` and `pnpm audit`. Resolve findings where possible. Require approval
  for accepted advisories, abandoned packages without a safe replacement, or fixes outside the
  approved scope. Treat development-only findings as best-effort and report anything left open.
- Prefer resolving advisories by updating lockfile resolutions within the reference constraints.
  If a fix requires changing a Laravel-owned manifest constraint, make the smallest compatible
  change, record it as an explicit reference exception, and obtain approval. Never replace all
  reference constraints with registry-latest constraints as an audit shortcut.
- After resolution and audit fixes, compare manifests and resolution settings with the approved
  snapshot and rerun the manifest-convergence gate. Investigate unexpected writes; accept only
  classified changes and documented exceptions. Keep shared minimum-version and migration
  requirements current when an audit fix changes what derived apps must install.
- Search code, configuration, scripts, CI, tests, and docs for every removed package and command.
- Delete `vendor` and `node_modules`, then run `composer install --no-interaction` in the container
  and `pnpm install --frozen-lockfile` on the host. This is the reproducibility test; do not delete
  either lockfile.

### 6. Refresh generated guidance and verify

- Run `docker compose exec -T app php artisan boost:update` after the clean install. Use
  `--discover` only when new packages may provide useful guidance; review interactive additions
  before accepting them.
- Run the narrow checks affected by manifest, script, and generated-file changes, then run:
  `mise run verify:backend`, `pnpm format:check`, `pnpm lint:check`, `pnpm types:check`,
  `pnpm test:unit`, `pnpm lint:docs`, and `pnpm test:e2e --reporter=dot`.
- Present the package disposition table, audit results, verification results, diff summary, and a
  proposed commit message.
- Commit the toolchain, manifests, lockfiles, and Boost resources only after explicit approval.
  Do not include skeleton synchronization in this commit.

## Phase 2: Laravel Skeleton Synchronization

### 1. Inventory every file difference

Compare the reference and repository recursively, including added, modified, deleted, renamed, and
mode-changed files. Produce a file disposition table with path, difference type, ownership,
action, evidence, behavior impact, and verification.

Classify each difference as:

- **Upstream-following**: Laravel defaults, starter scaffolding, configuration, comments, syntax,
  or files with no deliberate template divergence.
- **Template-preserved**: Docker/XServer support, synchronous production queues, deployment safety,
  project-specific auth, tests, docs, layering, or another capability identified in the policy
  reference and history.
- **Comparison noise**: only the runtime/generated artifacts excluded during reference creation.

No unclassified difference may remain. Absence from the new reference is a meaningful deletion,
not comparison noise.

### 2. Apply upstream changes

- Replace upstream-following files with the current Laravel form, including default comments and
  small configuration/style changes.
- Prefer compatible backend framework, bootstrap, and configuration improvements. Treat default
  frontend pages, components, layouts, hooks, and presentation as template-preserved unless this
  template actually reuses that surface or the user explicitly approves adopting its behavior.
- When Phase 1 adds a new Laravel-owned package, verify here that every related reference file and
  integration point was either absorbed or explicitly classified as template-preserved.
- Remove upstream-following files deleted by current Laravel when no retained template capability
  uses them.
- For template-preserved files, port compatible upstream syntax and defaults around the preserved
  behavior instead of replacing the whole file blindly. A file where the template is a stricter
  superset is not automatically "nothing to adopt": examine it line by line for portable upstream
  syntax, formatting, and path conventions to carry over around the preserved stricter rules.
- Never edit an already-shipped migration to adopt an upstream schema change; existing databases
  will not re-run it and the file would drift from the live schema. Add a new dated alter migration
  and verify both `up` and `down` against a fresh migrate plus rollback. First confirm the target
  table still exists in the template's final schema — some default framework tables are dropped
  intentionally (see the sync policy), so their upstream schema changes do not apply.
- Trace consumers before adopting a non-package skeleton file too. Do not add a `.gitignore` entry,
  generated-artifact ignore, or config keyed to a reference feature the template does not use (for
  example a fonts-manifest ignore tied to a build-time font plugin the template omits).
- Prefer current Laravel APIs and idioms while keeping observable behavior unchanged.
- Update related tests before behavior changes and obtain approval when behavior cannot be
  preserved.
- Re-run Wayfinder generation if routes or controller actions change.

### 3. Verify and finish

- Run the narrowest relevant checks after each subsystem, then the full verification listed in
  Phase 1, step 6.
- Re-run package audits if dependency files changed during skeleton synchronization.
- Search again for removed packages, files, config keys, classes, and script names.
- Repeat the full reference comparison and prove that every remaining difference is present in the
  file disposition table.
- Review `git diff --check`, `git diff --stat`, and the full `git diff`.
- Present the final disposition table, verification results, remaining intentional differences,
  and proposed commit message. Commit only after explicit approval.
- Delete both container and host reference directories after the comparison is accepted.
- Organize only ADRs and `CONTEXT.md` if the completed work changed a durable decision or domain
  language; do not create an ADR merely to log the upgrade.

## Required Report

Keep one concise report in the conversation throughout the run with:

1. baseline and selected reference options;
2. toolchain changes;
3. package disposition table;
4. non-dependency manifest disposition, including scripts, hooks, metadata, and empty values;
5. machine-generated proof that Laravel-owned manifest sections and constraints match the
   reference, including any approved exceptions;
6. audit findings and resolution;
7. file disposition table;
8. verification results;
9. derived-app requirements: necessary minimum versions or fixes, their reasons, and toolchain or
   targeted migration steps and where they are recorded; distinguish required adoption from
   routine lockfile refreshes;
10. blockers, approvals, and the two commit IDs when created.

Mark the update complete only when clean installs, required checks, and the final classified
reference comparison pass, and audits either pass or have explicit documented exceptions.

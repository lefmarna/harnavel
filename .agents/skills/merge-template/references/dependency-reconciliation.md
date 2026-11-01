# Dependency Reconciliation

Apply this policy on every template pull, even when git merges without conflicts. Convergence means
shared code and dependency requirements agree with the template and work with the app's own graph;
resolved versions may differ unless an explicit pin, override, or migration requirement makes them
part of the shared contract.

## Preserve the app baseline

Before pulling, record the app commit and its dependency inputs and lockfiles, uncommitted app
changes included. After a pull, recover the verified pre-pull state from the merge parents or
reflog; an upstream merge base is not the app baseline. Resolve an ambiguous baseline before
replacing a lockfile, and keep app dependency edits made since the pull.

Resolve from the app lockfiles even if git adopted or merged template lockfile changes: never splice
in template entries or delete a lockfile to force a fresh resolution. A missing or unusable app
lockfile is a recovery requirement to report, not a reason to discard known resolutions.

## Decide what must change

Compare the baseline with the merged inputs: Composer requirements, repositories, platform and
solver settings; every workspace manifest; pnpm workspace membership, catalogs, overrides, patches,
peer settings, and resolution hooks; plus toolchain changes and migration requirements in incoming
commits. A script- or autoload-only edit may need install or generation without version changes.

When the template removes a dependency that retained app code, scripts, configuration, or tests
still use, keep an app-owned direct declaration; a transitive lock entry or hoisting is not a
declaration. Reconcile shared and app constraints together, and never relax an app requirement to
make the solver succeed.

| Change | Action |
| --- | --- |
| No resolution input or required migration changed | Keep the app lockfiles; installing them is allowed |
| Addition, removal, constraint or resolution-setting change | Reconcile from the app lockfiles, keeping compatible resolutions |
| Template lockfile-only update | Inspect its intent; routine refreshes need no app adoption |
| Shared code needs a specific fix or minimum version | Verify the app meets it, or apply the dependency change with the shared code |
| Major toolchain or framework migration | Apply the official upgrade guidance's required app adaptations explicitly |

A required shared fix is not lost because the manifest did not change: prefer durable minimum or
excluded versions in template inputs, and record any targeted migration's packages, condition,
reason, and verification. Missing shared requirements go back to the template; convergence is not
declared while one remains unapplied.

## Reconcile and review

Use the project's pinned toolchain. Composer full-update `--minimal-changes` needs Composer 2.9 or
newer; with another version, resolve the toolchain requirement instead of silently running a routine
full update. A pnpm major does not by itself require a new lockfile.

Snapshot the agreed manifests and resolution settings, check hooks and settings that rewrite them
(including Composer's `bump-after-update`), and resolve only the affected ecosystem:

```bash
docker compose exec -T app composer update \
  --minimal-changes --no-install --no-scripts --no-interaction

pnpm install --lockfile-only --no-frozen-lockfile --ignore-scripts
```

These preserve resolutions where possible, not every line: necessary transitive moves can touch app
dependencies, and pnpm can keep several versions or rewrite peer snapshots. Use a scoped update only
for an identified shared fix or migration: Composer with package selectors and `-W -m` (keeping
`--no-install --no-scripts --no-interaction`), pnpm with `--no-save --lockfile-only`. Neither is
permission for unrelated maintenance.

After resolution, compare manifests and settings with the snapshot and investigate unexpected
writes rather than accepting them or restoring declarations over an incompatible lockfile. Review
direct and transitive additions, removals, and version changes against the baseline with a reason
per group. If the solver fails, diagnose the conflicting requirements rather than widening
constraints or discarding locks.

## Install, audit, and verify

```bash
docker compose exec -T app composer validate --strict
docker compose exec -T app composer install --no-interaction
pnpm install --frozen-lockfile
```

The final install honors the merged build policy and hooks, without script suppression. Composer
install does not replay `post-update-cmd`, so run required generation explicitly, keeping
template-owned skills verbatim if a generator would rewrite them, and verify no unintended manifest
or lockfile change followed.

Audit the baseline and final graph separately with `composer audit --locked` in Docker `app` and
host `pnpm audit`, distinguishing existing findings from new ones; an unavailable audit is not a
clean audit. Unrelated security fixes are a separate change, possibly preceding the merge; automatic
audit fixes and disabled advisory blocking are not ways to shrink a diff. If a vulnerable version
cannot be preserved, resolve the prerequisite within scope or report the merge blocked. Run the
app's relevant checks: a successful resolution alone does not prove behavior.

Report the baseline and tool versions, preserved declarations, lockfile change reasons, required
shared fixes, verification, and open findings, and note an app without a maintenance owner or
cadence; template merges do not replace dependency maintenance.

## Routine app-side maintenance

Between merges, an app updates resolutions, never shared declarations: a package the template also
declares carries the template's constraint string verbatim, and only packages the app alone
declares carry app-owned constraints.

`pnpm install --lockfile-only` refreshes the lockfile against the current manifest, and
`pnpm update --no-save --lockfile-only` refreshes resolutions within declared ranges. A
range-rewriting update such as `pnpm update --latest` raises shared floors silently and differently
in every app. Afterwards, confirm the manifest changed only for app-owned packages and the lockfile
still records each aliased or overridden importer's resolved specifier: pnpm 12.2.1 writes the raw
manifest range for the inherited `vite@*` override, so the next frozen install fails with
`ERR_PNPM_OUTDATED_LOCKFILE`; re-run plain `pnpm install --lockfile-only`, keeping the refreshed
resolutions, then verify with `pnpm install --frozen-lockfile`.

Widening a drifted shared constraint back to the template string downgrades nothing: the locked
version still satisfies it, so pnpm rewrites only the importer `specifier` lines.

A version the shared constraint does not allow is a shared requirement: record the package,
condition, and consumer evidence and take it to the template per
[the provenance policy](../../update-laravel-packages/references/provenance-and-sync-policy.md#shared-dependency-compatibility).

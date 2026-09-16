# Dependency Reconciliation

Use this policy for template pulls, including pulls that git resolves without conflicts.
Convergence means that shared code and dependency requirements agree with the template and work
with the app's own dependency graph. Exact resolved versions may differ unless an explicit pin,
override, or migration requirement makes them part of the shared contract.

## Preserve the app baseline

Before pulling, record the app commit and preserve its dependency inputs and lockfiles, including
any uncommitted app changes. If the pull already happened, recover the verified pre-pull app state
from the merge parents or reflog; an upstream merge base is not the app baseline. Resolve an
ambiguous baseline before replacing a lockfile. Account for app dependency edits made since the
pull; do not overwrite later app-owned lockfile changes with an older snapshot.

Use those app lockfiles as the resolution baseline even if git automatically adopted or merged
template lockfile changes. Do not splice in template entries or delete a lockfile to force a fresh
resolution. If an app lockfile is missing or unusable, report the recovery requirement separately
instead of silently discarding the known app resolutions.

## Decide what must change

Compare the app baseline with the merged dependency inputs: Composer requirements, repositories,
platform and solver settings; all workspace manifests; pnpm workspace membership, catalogs,
overrides, patches, peer settings, and resolution hooks. Inspect toolchain changes and explicit
template migration requirements in incoming commits and migration notes too. A script-only or
autoload-only edit may require install or generation without requiring package version changes.

Resolve ownership before dependency resolution. Trace consumers when the template removes a
dependency. If retained app code, scripts, configuration, or tests still use it, keep an app-owned
direct declaration in the appropriate manifest section. Do not rely on a transitive lock entry or
hoisting to keep an undeclared consumer working. Reconcile shared and app constraints together;
never relax an app requirement merely to make the solver succeed.

| Change | Action |
| --- | --- |
| No resolution input or required migration changed | Keep the app lockfiles unchanged; installing their existing resolutions is allowed |
| Addition, removal, constraint or resolution-setting change | Reconcile from the app lockfiles, preserving compatible resolutions where possible |
| Template lockfile-only update | Inspect its intent; routine refreshes do not require app adoption |
| Shared code needs a specific fix or minimum version | Verify the app meets that requirement; otherwise apply the necessary dependency change with the shared implementation |
| Major toolchain or framework migration | Read the affected versions' official upgrade guidance and apply its required app adaptations explicitly |

A required shared fix must not be lost because the manifest did not change. Prefer expressing
durable minimum versions or excluded broken versions in template manifests or resolution settings.
When a targeted migration is necessary, record the affected packages, required condition, reason,
and verification. Bring missing shared requirements back to the template; do not treat its entire
lockfile as the target or declare convergence while a required fix remains unapplied.

## Reconcile and review

Use the project's effective pinned toolchain. Composer full-update `--minimal-changes` requires
Composer 2.9 or newer. For another version, consult current official documentation and resolve the
toolchain requirement; do not fall back silently to a routine full update. A pnpm major change does
not by itself require a new lockfile: determine format, store, engine, and build-script migration
needs from the actual versions.

Once the merged manifests and resolution settings are agreed, snapshot them before invoking a
package manager. Inspect lifecycle hooks and settings that may rewrite them, including Composer's
`bump-after-update`. Resolve only the affected ecosystem. The default commands are:

```bash
docker compose exec -T app composer update \
  --minimal-changes --no-install --no-scripts --no-interaction

pnpm install --lockfile-only --no-frozen-lockfile --ignore-scripts
```

These commands preserve existing resolutions where possible, not every package or lockfile line.
Necessary transitive changes can affect app dependencies; pnpm can retain multiple versions or
rewrite peer snapshots. A compatible old version is not inherently inconsistent. Minimal changes
also do not mean choosing the lowest available version for a package that must move.

Use a scoped update only when an identified shared fix or migration requires it. For Composer,
keep `--no-install --no-scripts --no-interaction` and add package selectors with `-W -m`; this allows
related root dependencies to move and updates the named package even when its old version still
fits. For pnpm targeted updates, use `--no-save --lockfile-only` to preserve the agreed declarations
and review before installation. Verify the resulting lockfile. Neither a scoped update nor the
default commands are permission to perform unrelated maintenance.

Compare manifests and resolution settings with the merged-input snapshot after resolution. Do not
treat pnpm's `--lockfile-only` or suppressed scripts as a universal guarantee that manifests cannot
change. Investigate unexpected writes instead of silently accepting them or restoring declarations
over an incompatible new lockfile. Review direct and transitive additions, removals, version and
artifact changes against the app baseline, with a reason for each affected group. If the solver
fails, diagnose the conflicting requirements rather than widening constraints or discarding locks.

## Install, audit, and verify

After dependency reconciliation or a toolchain migration, validate and install the affected
ecosystem's reviewed resolutions in a clean environment without deleting either lockfile:

```bash
docker compose exec -T app composer validate --strict
docker compose exec -T app composer install --no-interaction
pnpm install --frozen-lockfile
```

Do not carry script suppression into the final install as a workaround. Honor the merged build
policy and app hooks. Composer install with a lockfile does not replay `post-update-cmd`; run
required asset or code generation explicitly after install. Keep template-owned skills verbatim:
if Boost or another generator would rewrite them, resolve that ownership conflict rather than
committing divergent shared instructions. Verify that install and generation did not introduce
unintended manifest or lockfile changes.

When reconciling dependencies, audit the preserved app baseline and final graph separately with
`composer audit --locked` in Docker `app` and host `pnpm audit`. Distinguish existing findings from
new ones; an unavailable audit is not a clean audit. Review peer warnings and run the app's relevant
checks from `AGENTS.md`; successful resolution or frozen install alone does not prove behavior.

Audit detection belongs in merge verification. Unrelated security fixes belong in a separately
scoped security change, which may need to precede the merge. Do not run automatic audit fixes or
disable advisory blocking to obtain a smaller diff. If policy prevents preserving a vulnerable
version, identify the security prerequisite and resolve it within the authorized scope or report
the merge blocked. Report unresolved findings and their disposition explicitly.

Keep shared code and its necessary dependency changes reviewable together. Separate unrelated
maintenance and security changes so regressions can be attributed. Report the baseline and tool
versions, preserved declarations, reasons for lockfile changes, required shared fixes, verification,
and outstanding findings. If the app has no maintenance owner or cadence, report that gap; template
merges do not replace ongoing dependency maintenance.

## Routine app-side maintenance

This section governs an app's own dependency maintenance between merges, not the merge itself.
Running it is app work; the rules below keep it from manufacturing the drift a later merge must
reconcile.

Constraint strings divide by provenance, not by which repository the file sits in. A package the
template also declares carries a shared constraint: the template owns that string, and the app
reproduces it verbatim. A package only this app declares carries an app-owned constraint the app
maintains freely. Routine maintenance therefore updates resolutions, never shared declarations.

Use commands that leave declarations alone. `pnpm install --lockfile-only` refreshes the lockfile
against the current manifest; `pnpm update --no-save --lockfile-only` refreshes resolutions within
the declared ranges. Do not use a range-rewriting update such as `pnpm update --latest` on shared
packages: it raises shared floors silently, and every app that runs it raises them differently.
After regenerating, confirm that the manifest is unchanged except for app-owned packages, and that
the lockfile still records each aliased or overridden importer's resolved specifier. pnpm 12.2.1
writes the raw manifest range instead, so the inherited `vite@*` override makes the next frozen
install fail with `ERR_PNPM_OUTDATED_LOCKFILE`; re-run plain `pnpm install --lockfile-only` to
restore it, keeping the refreshed resolutions. Then verify with `pnpm install --frozen-lockfile`.

Widening a shared constraint back to the template string does not downgrade anything. The locked
version still satisfies the wider range, so pnpm keeps it and rewrites only the importer
`specifier` lines. An app that has already drifted can therefore realign its declarations in one
step, and its resolutions stay where they are.

If the app needs a version the shared constraint does not allow, that is a shared requirement, not
a local fix. Record the package, the required condition, and the consumer evidence, and take it to
the template so every app gets it; see
[the provenance policy](../../update-laravel-packages/references/provenance-and-sync-policy.md#shared-dependency-compatibility).

## Official command references

- [Composer update](https://getcomposer.org/doc/03-cli.md#update-u-upgrade)
- [Composer full minimal updates](https://getcomposer.org/changelog/2.9.0-RC1)
- [Composer scripts](https://getcomposer.org/doc/articles/scripts.md)
- [pnpm install](https://pnpm.io/cli/install)
- [pnpm update](https://pnpm.io/cli/update)
- [pnpm audit](https://pnpm.io/cli/audit)

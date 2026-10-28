# Package Provenance and Skeleton Sync Policy

Use this repository-specific policy when running `update-laravel-packages`. Treat the freshly
generated application and git history as evidence; do not turn the examples below into permanent
package assumptions.

## Package provenance

| Classification | Deciding evidence | Default action |
| --- | --- | --- |
| Current Laravel-owned | The template adopts the reference capability without deliberate independent ownership | Use the reference constraint; choose the manifest section by the retained consumer's role |
| Unadopted reference-surface | The reference capability is omitted, replaced, or unsupported here | Omit its dedicated packages and companion files; record the consumer evidence |
| Legacy Laravel-owned | The fresh reference omits it and the repository's initial starter or an earlier Laravel synchronization introduced it | Remove it if unused; otherwise migrate to Laravel's current replacement |
| Template-owned | History establishes an independently maintained template capability, whether or not the reference now uses the package | Adopt the latest compatible stable release as the verified minimum |
| Unresolved | History, reference output, and usages do not establish ownership | Preserve and request a decision |

Package names can move between the first two Laravel-owned categories as the upstream starter
changes. Rebuild the package disposition table on every run.

## Shared dependency compatibility

Derived apps reconcile their own lockfiles through `merge-template`; they do not inherit this
template's exact resolved versions or perform routine full updates during a merge. For an adopted
shared capability, identify the required APIs and fixes from its consumers and upstream evidence.
Passing tests on the template's locked version does not establish compatibility with every older
version permitted by the reference constraint.

If the reference allows versions that cannot support retained shared behavior, use the smallest
compatible constraint or resolution-setting change that expresses the actual requirement. Record
the package, reference constraint, required condition, consumer or advisory evidence, and relevant
verification as a compatibility exception, and obtain approval before adopting it. This is an
exception to reference-string convergence, not a reason to raise every constraint to registry latest.

Express required minimum versions, exclusions, and coordinated pins in shared inputs. Persist only
non-obvious, mandatory derived-app actions that those inputs and shared code cannot convey, with
the relevant change; routine upgrades do not need migration documents. Keep execution findings in
the report. Routine lockfile refreshes do not require matching template resolutions. Explain
removals so derived apps can retain declarations for their own consumers.

## Known template-owned capabilities

These capabilities are deliberate. Preserve the capability, but do not assume its current package
is permanent if Laravel or a better maintained replacement now provides it.

| Capability | Current evidence to inspect | Compatibility requirements |
| --- | --- | --- |
| Japanese translations | `laravel-lang/lang`, `lang/`, locale configuration, commit `5e57fae` | Keep Japanese application and validation resources working |
| Static analysis | `larastan/larastan`, `phpstan.neon.dist`, Composer `analyse`, CI, commit `d18fdba` | Keep the configured Larastan/PHPStan analysis passing |
| Google authentication | `laravel/socialite`, Google auth services/routes/tests, commit `cf0705c` | Preserve the existing OAuth and account-linking behavior |
| Browser E2E tests | `@playwright/test`, `playwright.config.ts`, `e2e/`, CI, commit `5b75c37` | Keep smoke and auth browser coverage runnable |
| Docs and script checks | `tsx`, `scripts/`, package scripts, commits `196f853`, `b7ebda4`, `da48cc8` | Keep docs, deploy-safety, and unit script checks runnable |
| Git hooks | `vite-plus`, `.vite-hooks/`, the `staged` block of `vite.config.ts`, package `prepare`/`check:hooks`, TADR-0009 | Keep local hook installation without affecting production deploys |
| Frontend toolchain | `vite-plus`, the `lint`/`fmt` blocks of `vite.config.ts`, the `vite@*` override in `pnpm-workspace.yaml`, TypeScript configs, package `format`/`lint`/`types:check` scripts, TADR-0009 | Keep Vite+ as the single source of Vite, Oxfmt, Oxlint, and `oxlint-tsgolint`; keep Oxfmt the only formatter and type-aware Oxlint the only linter, and preserve the semantic guardrails configured in the `lint` block |

If history after these commits changes the capability, follow the latest evidence rather than the
table.

## Skeleton ownership

### Preserve as template behavior

- Docker development through nginx, PHP-FPM, and MariaDB.
- XServer deployment constraints: no SSR, queue workers, or other long-running production
  processes; synchronous jobs; protected remote data during `rsync --delete`; bounded logs, because
  the host has limited disk and no external rotation. `.env.example` therefore deviates from
  upstream's unrotated logging default; track upstream's rotation mechanism, do not drop it.
- The template ships no queue tables (`jobs`, `job_batches`, `failed_jobs`) because queues run
  synchronously. Do not adopt upstream migrations for them.
- Host/container command boundary from `AGENTS.md`.
- Japanese README and tests, English engineering docs, testing-as-spec workflow, ADR policy, and
  domain glossary policy.
- Project-specific Google authentication, SEO, observability, verification, CI, deployment, and
  test helpers.
- Existing user-visible application and authentication flows, including success feedback, unless
  the replacement behavior and its derived-application rollout are explicitly approved.
- Frontend pages, components, layouts, hooks, and presentation that this template implements
  independently from Laravel's default starter UI.
- Layer decisions and PageViewModel/Wayfinder conventions established by Template ADRs, tests, and
  existing `app/ViewModels`.

### Follow upstream by default

- Laravel configuration ordering, type declarations, bootstrapping idioms, and
  starter scaffolding that do not conflict with a preserved capability.
- New Laravel files and settings unless they require unsupported infrastructure or change retained
  behavior.
- Removal of obsolete Laravel files, settings, scripts, and packages when no preserved capability
  uses them.

### Exclude only as comparison noise

- `.git`, `.env`, `vendor`, `node_modules`, build outputs, caches, logs, generated SQLite database,
  test reports, and temporary files.

Do not exclude `.env.example`, dotfiles, lockfiles, agent instructions, generated configuration,
or files merely because the template currently lacks them.

## Required evidence for removals

Establish provenance, retained consumers, and the replacement or reason none is needed. Inspect
scripts, configuration, tests, and CI as well as application code. Reference presence does not
prevent removal of an unused dependency, unsupported command, or setting covered by current
defaults. Use relevant existing checks; do not add tests that merely duplicate configuration.
Request a decision only when ownership or behavior impact remains unresolved.

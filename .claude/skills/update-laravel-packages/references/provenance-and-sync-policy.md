# Package Provenance and Skeleton Sync Policy

Use this repository-specific policy when running `update-laravel-packages`. Treat the freshly
generated application and git history as evidence; do not turn the examples below into permanent
package assumptions.

## Package provenance

| Classification | Deciding evidence | Default action |
| --- | --- | --- |
| Current Laravel-owned | The fresh reference contains the package in the same capability area | Adopt the reference constraint and section |
| Legacy Laravel-owned | The fresh reference omits it and the repository's initial starter or an earlier Laravel synchronization introduced it | Remove it if unused; otherwise migrate to Laravel's current replacement |
| Template-owned | A post-starter commit introduced it for an explicit retained template capability | Update to the latest compatible stable version |
| Unresolved | History, reference output, and usages do not establish ownership | Preserve and request a decision |

Package names can move between the first two Laravel-owned categories as the upstream starter
changes. Rebuild the package disposition table on every run.

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
| Git hooks | `lefthook`, `lefthook.yml`, package `prepare`, commit `3f4643a` | Keep local hook installation without affecting production deploys |

If history after these commits changes the capability, follow the latest evidence rather than the
table.

## Skeleton ownership

### Preserve as template behavior

- Docker development through nginx, PHP-FPM, and MariaDB.
- XServer deployment constraints: no SSR, queue workers, or other long-running production
  processes; synchronous jobs; protected remote data during `rsync --delete`.
- Host/container command boundary from `AGENTS.md`.
- Japanese README and tests, English engineering docs, testing-as-spec workflow, ADR policy, and
  domain glossary policy.
- Project-specific Google authentication, SEO, observability, verification, CI, deployment, and
  test helpers.
- Layer decisions and PageViewModel/Wayfinder conventions established by Template ADRs and tests.
- The one-line `CLAUDE.md` and `GEMINI.md` pointers to `AGENTS.md`.

### Follow upstream by default

- Laravel default comments, configuration ordering, type declarations, bootstrapping idioms, and
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

Before removing a package, script, config key, or file:

1. Show that the fresh reference omits it.
2. Identify the commit or older starter that introduced it.
3. Search code, config, scripts, tests, CI, and docs for consumers.
4. Explain the replacement or why no replacement is needed.
5. Name the checks that prove retained behavior still works.

If any item cannot be completed, classify the removal as unresolved and obtain approval.

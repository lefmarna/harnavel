# Package Provenance and Skeleton Sync Policy

Repository-specific policy for `update-laravel-packages`. The fresh reference and git history are
the evidence; package names move between categories as the starter changes, so rebuild the
disposition table on every run.

## Package provenance

| Classification | Deciding evidence | Default action |
| --- | --- | --- |
| Current Laravel-owned | The template adopts the reference capability without independent ownership | Use the reference constraint; choose the section by the retained consumer's role |
| Unadopted reference-surface | The reference capability is omitted, replaced, or unsupported here | Omit its dedicated packages and companion files; record the consumer evidence |
| Legacy Laravel-owned | Absent from the fresh reference and introduced by the initial starter or an earlier sync | Remove if unused; otherwise migrate to Laravel's current replacement |
| Template-owned | History establishes an independent template capability, whether or not the reference uses the package | Adopt the latest compatible stable release as the verified minimum |
| Unresolved | History, reference, and usages do not establish ownership | Preserve and ask the user |

## Shared dependency compatibility

Derived apps reconcile their own lockfiles through `merge-template` and do not inherit the
template's resolved versions. Passing tests on the template's locked version does not prove every
older version the reference constraint permits, so identify the APIs and fixes retained shared
behavior requires.

When the reference allows versions that cannot support it, adopt the smallest compatible constraint
or resolution-setting change, recorded as an approved compatibility exception with the package,
reference constraint, required condition, and evidence. It is an exception to reference-string
convergence, not a reason to raise every constraint to registry latest. Persist only mandatory
derived-app actions that shared inputs cannot convey, and explain removals so apps can keep
declarations for their own consumers.

## Known template-owned capabilities

Preserve each capability; its package may change when Laravel or a better maintained replacement
provides it. If later history changes a capability, follow that evidence over this table.

| Capability | Evidence | Requirement |
| --- | --- | --- |
| Japanese translations | `laravel-lang/lang`, `lang/`, locale config | Japanese application and validation resources keep working |
| Static analysis | `larastan/larastan`, `phpstan.neon.dist`, `composer analyse` | The configured analysis keeps passing |
| Google authentication | `laravel/socialite`, Google auth services, routes, tests | OAuth and account-linking behavior is unchanged |
| Browser E2E tests | `@playwright/test`, `playwright.config.ts`, `e2e/` | Smoke and auth browser coverage stays runnable |
| Docs and script checks | `tsx`, `scripts/`, package scripts | Docs, deploy-safety, and unit script checks stay runnable |
| Git hooks | `vite-plus`, `.vite-hooks/`, the `staged` block of `vite.config.ts`, `prepare`/`check:hooks` | Hooks install locally without affecting production deploys |
| Frontend toolchain | `vite-plus`, the `lint`/`fmt` blocks of `vite.config.ts`, the `vite@*` override in `pnpm-workspace.yaml`, TADR-0009 | Vite+ stays the single source of Vite, Oxfmt, Oxlint, and `oxlint-tsgolint`, with the `lint` block's guardrails |

## Skeleton ownership

- **Template-preserved**: Docker development (nginx, PHP-FPM, MariaDB, Mailpit); XServer
  constraints (no SSR, queue workers, or long-running processes; synchronous jobs; remote data
  protected during `rsync --delete`; bounded logs, because the host has limited disk and no external
  rotation, so `.env.example` deviates from upstream's unrotated default while tracking its rotation
  mechanism); no `jobs`/`job_batches`/`failed_jobs` migrations; the host/container boundary;
  Japanese README and tests with English docs; Google authentication, SEO, observability,
  verification, CI, deployment, and test helpers; existing user-visible flows, including success
  feedback, unless a replacement and its derived-app rollout are approved; independently
  implemented frontend pages, components, layouts, hooks, and presentation; and the layering and
  PageViewModel/Wayfinder conventions of the Template ADRs.
- **Upstream-following**: Laravel configuration ordering, type declarations, bootstrap idioms, new
  files and settings, and removal of obsolete files, settings, scripts, and packages, as long as no
  preserved capability conflicts or needs unsupported infrastructure.
- **Comparison noise**: `.git`, `.env`, `vendor`, `node_modules`, build outputs, caches, logs, the
  generated SQLite database, test reports, and temporary files. `.env.example`, dotfiles,
  lockfiles, agent instructions, generated configuration, and files the template merely lacks stay
  in the comparison.

## Removals

A removal needs provenance, the retained consumers checked (scripts, configuration, tests, and CI
as well as code), and the replacement or the reason none is needed. Reference presence does not
block removing an unused dependency, an unsupported command, or a setting current defaults cover.
Existing checks verify it; a test that merely duplicates configuration adds nothing.

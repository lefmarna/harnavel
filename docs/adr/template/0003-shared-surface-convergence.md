# Shared-surface convergence for derived projects

## Status

Accepted.

## Context

Multiple projects derive from this template and adopt its updates via `git pull template main` +
`/merge-template` (see [`TADR-0001`](0001-development-workflow.md)). As each project
implemented the test-as-spec methodology independently, the **template-origin surface drifted**
across them: the same shared file — `app/Services/GoogleAuth/GoogleAuthService.php`, the
Fortify/Settings backend, the auth/settings test suites, the CI workflows — acquired divergent
comments, variable names, test ordering, and equivalent-mutant annotations in each project.

Unmanaged drift makes every future template pull conflict, eroding the propagation mechanism
TADR-0001 depends on. The goal of convergence is that a future `git pull template main` produces
**no judgment-requiring conflicts**; the only conflicts left resolve mechanically.

## Decision

The template is the **canonical source for the shared surface**. Derived projects converge to it
by pull + `/merge-template`; an improvement that matured in one project is upstreamed to the
template (best version wins — prefer *eliminating* an equivalent mutant by simplification over
*annotating* it). Every shared file falls into one of three tiers:

- **Tier 1 — fully shared, byte-identical target.** Files with no legitimate app-specific
  content: test infrastructure (`tests/TestCase.php`), shared auth/settings tests,
  `app/Concerns/PasswordValidationRules`, `app/Http/Requests/Settings/*`,
  `app/Services/GoogleAuth/*` (minus app hunks), generic `resources/js/components/ui/*`
  primitives, repo tooling scripts (`scripts/lint-docs.ts`, `scripts/hooks/*`), CI reusable
  test/mutation workflows and `.github/actions/*` (minus calibration — see below; `check.yml`,
  `quality.yml`, and `delivery.yml` are Tier 2). For shared behavioural tests the canonical
  version is the **most thorough, correct** one; genuinely app-specific cases (i18n, app
  validation, disabled features) stay app-side.
- **Tier 2 — hybrid.** Files mixing template scaffolding with app content (`app/Models/User.php`,
  `FortifyServiceProvider`, Settings controllers, `EloquentUserRepository`, `tests/Pest.php` —
  scaffolding plus app test helpers, `tests/Feature/Architecture/ArchTest.php` — shared rules
  plus reasoned app-side `->ignoring()` hunks, `scripts/test-deploy-rsync-excludes.ts` — shared
  logic plus app-calibrated protected paths, the page-ViewModel scaffolding under
  `app/ViewModels`, `.github/workflows/delivery.yml` — shared deploy structure plus
  app-calibrated sibling-subdomain excludes, release-critical asset lists, and app-specific
  steps; its app exclude set pairs with the rsync contract test's `PROTECTED_PATHS`,
  `.github/workflows/check.yml` and `.github/workflows/quality.yml` — shared `ci-lint` job
  structure plus app-specific steps, and `docs/adr/README.md` — shared ADR policy text plus
  app-owned ADR index sections). Converge the template-origin hunks only; keep the app hunks.
  These are never byte-identical.
- **Tier 3 — app-owned, out of scope.** Presentation, branding, and domain: `welcome` /
  `dashboard` / domain pages, branding components, `resources/css` design tokens, `public/*`,
  `routes/web.php`, lockfiles, `.env.example`, `README`.

**Calibration carve-out.** The coverage/mutation gate values (`--min`) and the mutation-blocking
toggle (`continue-on-error`), together with their adjacent rationale comments, are **calibrated
per project** (TADR-0001: each project's CI workflow is the single source of truth for its gate).
The reusable test/mutation workflow *files* (`reusable-test.yml` / `mutation.yml`) lean template,
but these *lines* stay app-priority. `/merge-template` records this exception so pulls resolve
them deterministically.

The **density** of `@pest-mutate-ignore` annotations is likewise calibration-coupled: a higher
`--min` forces a project to annotate equivalent survivors that a lower floor can leave
un-annotated. Only the code and the equivalent-mutant patterns
([`docs/testing-as-spec.md`](../../testing-as-spec.md)) converge — the per-line annotation set
stays app-priority.

**Zero-conflict** therefore means: no conflicts on Tier 1 or the shared hunks of Tier 2.
Calibration lines and Tier 3 files may still conflict on pull, but they resolve mechanically in
the app's favour — no human judgment.

The Tier 1 / 2 / 3 path classifications and calibration-line patterns are maintained in machine-readable
form in [`.shared-surface.yml`](../../../.shared-surface.yml), which `/merge-template` and
`/absorb-from-apps` treat as the authoritative tier reference.

## Considered alternatives

- **Extract calibration values out of the pulled files** (GitHub Actions `vars`/inputs) so the
  workflow YAML is byte-identical. Rejected for now: more CI wiring, and an inline value is
  easier to read and review. Revisit if calibration churn becomes painful.
- **Unify the thresholds across all projects.** Rejected: it contradicts TADR-0001's per-project
  calibration — a content app and a compiler service have different defensible mutation-score
  ceilings.
- **Converge everything, including Tier 3.** Rejected: presentation/branding/domain is
  intentionally app-specific; forcing byte-identity there erases each app's identity for no
  propagation benefit.

## Consequences

- Upstreaming a shared improvement generates one-time downstream conflicts in apps that changed
  the same spot differently; they resolve via `/merge-template`, after which pulls are clean.
- A shared test and the production code it specifies move together — a more thorough test is
  upstreamed only with the implementation that keeps the template green (TADR-0001: spec × green).
- The per-app convergence sweep is a one-time effort and is **not** recorded in repo docs
  (TADR-0001: no transient status in docs).

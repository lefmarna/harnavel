# Development workflow: tests are the executable specification

## Status

Accepted. Consolidates the template's development-workflow decisions, including the
retired stance that made `kiro-discovery` the mandatory entry point (see Kiro's place).

## Decision

Treat test code as the canonical specification of system behavior. The goal is to reduce
review burden: a reviewer should be able to approve the **test cases** (read the test
tree and assertions) and confirm they pass, without reading the implementation.

Human review is **front-loaded** onto the spec, not removed:

1. From a short natural-language intent, author the spec as `it('...')->todo()` cases
   (the title tree plus assertion sketch).
2. The reviewer approves that spec **before implementation**.
3. Implement red → green (`tdd` skill).
4. CI gates stand in for reading the implementation (see Gates below); require only the
   checks currently wired in the repo.
5. Approved spec × green × the wired gates passing → merge without reading the
   implementation.
6. After completion, organize **only** ADRs and `CONTEXT.md`.

Key boundaries:

- **Canonical layer:** Pest Feature tests. E2E (Playwright) stays smoke + a few critical
  happy paths. Pure client-side React interaction is **out of scope** of the spec.
- **Readers:** engineers + AI agents only. No generated human-readable spec docs.
- **Structure:** `describe`/`it` hierarchy for new tests; Japanese behavioral titles
  (`〜できる`, `〜の場合〜になる`); one `it` = one behavior.
- **Coverage:** high floor (~90%) on `app/`; 100% is aspirational on pure logic layers
  (`Services`/`Actions`/`Repositories`); honesty is backed by mutation testing (see
  Gates). **Global 100% line coverage is explicitly rejected** because it rewards
  tautological tests and gives false confidence.
- **Coverage contract:** behavioral changes (endpoints, validation, authorization,
  business rules, Inertia contract) MUST have a test = "untested means unspecified".
  Styling/copy/layout, config/infra, and docs are exempt. New pages still require a smoke
  E2E test.

The full convention lives in [`docs/testing-as-spec.md`](../../testing-as-spec.md).

## Gates

The full gate set: green tests + coverage floor + Pest `arch()` + Larastan + scoped
mutation testing. A project enables them incrementally (non-blocking → blocking); only
the currently wired checks are required for merge.

- **Calibration is per project.** Each project's CI workflow is the single source of
  truth for its gate values — the coverage and mutation-score floors (`--min`) and
  whether mutation blocks the build (`continue-on-error`). These lines stay app-side on
  template pulls ([`TADR-0003`](0003-shared-surface-convergence.md)).
- **Mutation scope:** the pure-logic layers (`app/Services`, `app/Actions`,
  `app/Repositories`), run as a separate workflow on PRs touching those layers or their
  tests, plus manual dispatch.
- **Mutation tool:** Pest's built-in `pest --mutate` (`pestphp/pest-plugin-mutate`),
  **not** standalone `infection/infection` — Infection 0.33 is structurally incompatible
  with Pest 4 (it cannot reconcile Pest's `P\…` evaluable test-class names against its
  JUnit report, `TestNotFound`, and ships no Pest adapter). `pest --mutate` uses the same
  mutation-score (MSI) concept and honours `@pest-mutate-ignore` for equivalent mutants.

## Kiro's place

Kiro's `requirements`/`design` phases were once the mandatory entry point for new work;
they are now an **optional upfront design aid**, used when they help. Tests remain the
single canonical spec: Kiro artifacts are upfront working documents, never reconstructed
after the fact as documentation — durable knowledge consolidates into ADRs +
`CONTEXT.md` instead.

## Consequences and accepted risks

- A wrong spec is only caught at the upfront approval gate — that gate is the last line of
  defense.
- Untested paths beyond the coverage floor are accepted; dangerous patterns are caught by
  `arch()` and Larastan instead of human reading.
- "Green = correct" depends on test honesty, which is enforced by mutation testing (scoped
  to logic layers) and a minimal-mocking convention.

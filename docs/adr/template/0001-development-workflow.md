# Development workflow: tests are the executable specification

## Status

Accepted. Consolidates the template's development-workflow decisions and supersedes the
earlier stance that made `kiro-discovery` the mandatory entry point (see History).

## Decision

Treat test code as the canonical specification of system behavior. The goal is to reduce
review burden: a reviewer should be able to approve the **test cases** (read the test
tree and assertions) and confirm they pass, without reading the implementation.

Human review is **front-loaded** onto the spec, not removed:

1. From a short natural-language intent, author the spec as `it('...')->todo()` cases
   (the title tree plus assertion sketch).
2. The reviewer approves that spec **before implementation**.
3. Implement red → green (`tdd` skill).
4. CI gates stand in for reading the implementation. The target set, enabled across the
   rollout below, is: green tests **+** coverage floor **+** Pest `arch()` **+** Larastan
   **+** (scoped) mutation testing — require only the gates currently wired.
5. Approved spec × green × the currently wired gates passing → merge without reading the
   implementation.
6. After completion, organize **only** ADRs and `CONTEXT.md`.

Key boundaries:

- **Canonical layer:** Pest Feature tests. E2E (Playwright) stays smoke + a few critical
  happy paths. Pure client-side React interaction is **out of scope** of the spec.
- **Readers:** engineers + AI agents only. No generated human-readable spec docs.
- **Structure:** `describe`/`it` hierarchy for new tests; Japanese behavioral titles
  (`〜できる`, `〜の場合〜になる`); one `it` = one behavior.
- **Coverage:** high floor (~90%) on `app/`; 100% is aspirational on pure logic layers
  (`Services`/`Actions`/`Repositories`); honesty is backed by mutation testing
  (Infection). **Global 100% line coverage is explicitly rejected** because it rewards
  tautological tests and gives false confidence.
- **Coverage contract:** behavioral changes (endpoints, validation, authorization,
  business rules, Inertia contract) MUST have a test = "untested means unspecified".
  Styling/copy/layout, config/infra, and docs are exempt. New pages still require a smoke
  E2E test.

The full convention lives in [`docs/testing-as-spec.md`](../../testing-as-spec.md).

## History

The template initially made `kiro-discovery` the **mandatory** entry point for new work —
turning raw ideas into Kiro requirements/design before implementation. Test-first replaced
that *mandatory* status, but **Kiro is not removed**. Its `requirements`/`design` phases
remain available as an **optional upfront design aid**, used when they help (not every
change needs them). Using Kiro does **not** conflict with this workflow: tests remain the
single canonical spec, and any `requirements`/`design` are upfront working artifacts, not a
competing spec. The only thing avoided is **reconstructing** `requirements`/`design`/`tasks`
after the fact as documentation — durable knowledge consolidates into ADRs + `CONTEXT.md`
instead.

## Consequences and accepted risks

- A wrong spec is only caught at the upfront approval gate — that gate is the last line of
  defense.
- Untested paths beyond the coverage floor are accepted; dangerous patterns are caught by
  `arch()` and Larastan instead of human reading.
- "Green = correct" depends on test honesty, which is enforced by mutation testing (scoped
  to logic layers) and a minimal-mocking convention.

## Rollout

Phased so the methodology can propagate to projects derived from this template without
breaking their CI:

- **Phase 1 (current): conventions only, no CI change** — this ADR, `docs/testing-as-spec.md`,
  a thin pointer in `AGENTS.md`, and `/merge-template` classification. Flows to derived
  projects via `git pull template main` + `/merge-template`.
- **Phase 2:** `arch()` rules (written to pass current code).
- **Phase 3:** migrate existing tests (hybrid: structural reshape preserving assertions),
  then add the coverage gate (non-blocking → blocking).
- **Phase 4:** Infection scoped to logic layers, wired into CI.

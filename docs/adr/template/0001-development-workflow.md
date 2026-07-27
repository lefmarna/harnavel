# Development workflow: tests are the executable specification

## Status

Accepted.

## Decision

Treat Pest Feature tests as the canonical specification of system behavior. Human review is
front-loaded onto the test cases before implementation; once approved, passing tests and the
automated gates currently wired in the project replace routine implementation review.

Behavioral changes within this specification model—server logic, validation, authorization, and
Inertia component/props contracts—must have a Feature test. Playwright remains a smoke layer plus a
small number of critical happy paths, and pure client-side interaction is outside this model. Kiro
requirements and design artifacts may help before implementation, but they are working material
rather than another canonical specification. Durable knowledge is retained only in ADRs and
`CONTEXT.md`.

The target gate set is green tests, a coverage floor, architecture tests, Larastan, and scoped
mutation testing. Projects adopt these gates incrementally and require only those already wired.
Each project's CI workflow is authoritative for its coverage and mutation thresholds and for
whether mutation failures block merging; those calibrated values remain app-owned under
[`TADR-0003`](0003-shared-surface-convergence.md).

Gates are limited to checks whose verdict is fixed by the repository content. A check whose result
can change while the code does not — a third-party advisory published against a transitive
dependency — is detected on a schedule instead, because gating on it makes the ability to ship
depend on when someone else publishes.

Mutation testing uses Pest's mutation plugin and focuses on pure-logic layers. The operational
workflow, test conventions, and mutation guidance live in
[`docs/testing-as-spec.md`](../../testing-as-spec.md).

## Considered alternatives

- **Require implementation review for every change.** Rejected because an approved executable
  specification plus automated gates makes that review redundant for routine changes.
- **Require global 100% line coverage.** Rejected because it rewards tautological tests and gives
  false confidence; mutation testing provides a stronger honesty check.
- **Maintain generated human-readable specifications or Kiro artifacts after implementation.**
  Rejected because either would duplicate the executable specification and drift from it.
- **Use standalone Infection for mutation testing.** Rejected because its Pest 4 test discovery and
  JUnit reconciliation produced `TestNotFound` for Pest-generated test classes; Pest's integrated
  runner avoids that second discovery integration.
- **Gate dependency audits (`composer audit` / `pnpm audit`) in CI.** Rejected because their verdict
  depends on an advisory database rather than the tree: an unchanged commit turns red overnight, and
  an advisory with no released fix blocks every merge and every deployment, including an emergency
  fix for an unrelated production incident. A derived application lost its ability to deploy for a
  week this way. Detection moved to a scheduled workflow.
- **Run an automated dependency-update bot.** Rejected together with the gate above: constraint
  strings are classified against a fresh upstream reference by an explicit skill, and a bot
  configured to stay inside those constraints still needs its own policy rules and a test proving
  those rules hold. The daily audit reports what needs attention; a human applies it.

## Consequences and accepted risks

- A wrong specification may pass every later gate, so approval of the test cases is the critical
  human checkpoint.
- Untested paths beyond the coverage floor are accepted; architecture tests and static analysis
  cover structural risks rather than behavioral completeness.
- Confidence in green tests depends on mutation testing and restrained mocking exposing weak or
  tautological assertions.
- A pull request that introduces a vulnerable dependency is not blocked; detection lags by up to the
  audit schedule's interval, and that scheduled workflow is the only path, so its silent disablement
  or an undelivered failure notification removes detection entirely. Dependencies also no longer
  move on their own, so routine updates accumulate until an advisory or a deliberate update prompts
  them.

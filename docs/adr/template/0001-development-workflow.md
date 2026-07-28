# Development workflow: tests are the executable specification

## Status

Accepted.

## Decision

Treat Pest Feature tests as the canonical specification of system behavior. Human review is
front-loaded onto the test cases before implementation, and for routine low-risk changes the
approved specification plus the automated gates carry the change without a second reading.

They do not carry everything. A green suite proves the implementation satisfies the cases someone
thought to write; it is silent about the case nobody wrote, and gates cannot audit their own wiring.
**Implementation review is required** — not weighed against risk, not waived by a green run — for
changes that touch authentication, authorization or session issuance; secrets; CI, delivery, or
anything deciding whether a release ships; migrations and other destructive or irreversible data
operations; transactions, locking, and concurrency; outbound side effects (mail, HTTP, filesystem,
third-party APIs); or the test and gate machinery itself. Everything outside that list is judged on
risk: read the implementation when the change is unfamiliar, wide, or hard to reverse, and rely on
the spec when it is narrow and local.

Coverage and mutation scores are **inputs to that judgment, never a substitute for it**. Both
measure how thoroughly the written cases exercise the code; neither can measure the case that was
never written.

Pre-approval of `it(...)->todo()` cases applies to **behavioral** changes. A mechanical refactor that
preserves behavior — renaming, moving a file, extracting a helper, deleting dead code — is approved
by the existing suite staying green, not by authoring todo cases first.

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
- **Let the approved spec and the gates replace implementation review everywhere.** This was the
  original decision, reversed on evidence: a 2026-07 audit found four defects a fully green suite
  had carried — an E2E route minting authenticated sessions on any reachable development machine,
  `pnpm test:unit` missing from the only workflow that deploys, a maintenance responder reading one
  media range of `Accept`, and a deployment test putting the working application into maintenance
  mode. Each was a case nobody thought to specify, in exactly the areas now listed as
  review-required. Coverage and mutation are computed from the cases that exist, so neither could
  have surfaced any of them.
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

- A wrong specification may pass every later gate, so approval of the test cases remains the primary
  human checkpoint — but no longer the only one for the review-required areas above.
- Requiring implementation review for those areas costs reviewer time on the changes most likely to
  need it, and depends on the boundary being read honestly: a change that quietly touches
  authentication or delivery while presenting as routine escapes the rule.
- Untested paths beyond the coverage floor are accepted; architecture tests and static analysis
  cover structural risks rather than behavioral completeness.
- Confidence in green tests depends on mutation testing and restrained mocking exposing weak or
  tautological assertions.
- A pull request that introduces a vulnerable dependency is not blocked. Detection lags by up to the
  audit schedule's interval on the branches the delivery workflow deploys, which the schedule must
  therefore check explicitly: it only ever starts on the default branch, so any other deployed branch
  is unscanned until the schedule is pointed at it. Work on branches that are not deployed is not
  scanned at all until it reaches one that is. The scheduled workflow is also the only path, so its
  silent disablement or an undelivered failure notification removes detection entirely. Dependencies
  no longer move on their own either, so routine updates accumulate until an advisory or a deliberate
  update prompts them.

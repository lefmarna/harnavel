# ADR lifecycle: defer authoring, squash provisional decisions

## Status

Accepted.

## Context

This template makes ADRs a standing practice for derived projects: after completion, work
organizes **only** ADRs and `CONTEXT.md` ([`TADR-0001`](0001-development-workflow.md)).
In practice, specs change rapidly — a provisional spec ships and is revised shortly after.

If every provisional decision is recorded as an ADR, and every revision leaves a
`Superseded` tombstone behind, the ADR set fills with entries that never had real-world
consequences. Some derived projects already approach ~20 ADRs, where the noise from
short-lived decisions makes the durable ones hard to find.

Strict ADR doctrine is append-only: never delete, always supersede. That doctrine assumes
each recorded decision had consequences worth preserving. It breaks down when ADRs are
authored before a decision stabilizes — the history of a never-shipped reversal has no
value to preserve.

## Decision

Two rules govern ADR authoring and lifecycle so the set converges on current,
consequential decisions with minimal tombstones.

1. **Defer authoring until durable.** The canonical spec is test code (TADR-0001); a
   rapidly-changing spec lives in tests + `CONTEXT.md`, which already retain only the
   current state because old tests are rewritten when behavior changes. Author or update an
   ADR only once a decision is **durable** — it has shipped, survived at least one
   iteration, or other work depends on it. A direction expected to flip is not yet a
   decision and stays out of the ADR set. This sharpens AGENTS.md's existing "create or
   update ... ADRs *lazily* as ... decisions are resolved".

2. **Squash provisional supersessions.** When a decision is superseded, keep a `Superseded`
   tombstone only if the old decision had real-world consequences — it shipped, or another
   ADR / design / code depends on it. If it was provisional (reverted before shipping, with
   nothing depending on it), overwrite or delete it instead of leaving a tombstone.

`Status` lifecycle: `Proposed` → `Accepted` → `Superseded by ADR-NNNN` / `Deprecated`. A
`Proposed` ADR dropped before acceptance is deleted, not tombstoned.

This relaxes pure append-only deliberately: it is preserved exactly where it earns its keep
(decisions that had consequences) and dropped where it would only add noise (provisional
decisions that never did).

## Considered alternatives

- **Strict append-only (never delete).** Rejected: it preserves history for decisions that
  never had consequences, producing the very noise this ADR addresses. A never-shipped,
  unreferenced reversal has no historical value.
- **Forbid ADRs for anything spec-like; route all churn to tests only.** Rejected as too
  blunt: some architectural decisions are durable from the start and deserve a record before
  they ship. The defer-until-durable trigger captures that without banning early-but-stable
  ADRs.
- **Add an index/dashboard of all ADRs (auto-generated or manual in `docs/adr/README.md`).**
  Deferred: an index treats the symptom (scanning ~20 entries) rather than the cause (too
  many entries). Revisit once authoring discipline lands and the durable-only count is
  genuinely hard to navigate.

## Consequences

- The ADR set trends toward current, consequential decisions; provisional churn leaves no
  residue. "Only the final spec remains" holds at the spec layer automatically (tests) and
  at the decision layer via the squash rule.
- Classifying "had consequences" vs "provisional" needs judgment. The shipped /
  depended-upon test is the tiebreaker; when in doubt, keep the tombstone.
- Deleting a provisional ADR can break a reference if one was added prematurely. The
  defer-until-durable trigger makes premature references unlikely; gap closure follows the
  renumbering guidance in [`docs/adr/README.md`](../README.md).

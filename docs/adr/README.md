# Architecture Decision Records

This README is the single policy document for decision records: namespaces, when a
decision deserves an ADR, lifecycle, and how derived projects adopt template updates.

## Namespaces

Two independent ADR namespaces share this tree so that the template and projects derived
from it never collide on numbering:

- **Template ADRs** — `docs/adr/template/NNNN-*.md`. Owned by the template; they flow into
  derived projects via `git pull template main` + `/merge-template`. Referenced as
  **TADR-NNNN**.
- **App ADRs** — `docs/adr/NNNN-*.md` (this directory, flat). Owned by the individual
  project for its own decisions. Referenced as **ADR-NNNN**.

A derived project records its own decisions as App ADRs here and never edits Template ADRs
(they always merge to the template side). Because the two namespaces are separate
directories, an App ADR and a Template ADR may share a number without conflicting.

## When to write an ADR

ADRs record **durable decisions**, not in-flight specs. The canonical spec is test code
([`TADR-0001`](template/0001-development-workflow.md)); a rapidly-changing spec lives in
tests + [`CONTEXT.md`](../../CONTEXT.md), which keep only the current state because old
tests are rewritten when behavior changes. Author or update an ADR only once a decision is
**durable** — it has shipped, survived at least one iteration, or other work depends on it.
A direction expected to flip is not yet a decision; keep it out of the ADR set.

Not every durable fact is an ADR. Constraints every session needs go in `AGENTS.md`,
domain terms in `CONTEXT.md`, how-tos and reference facts in `docs/`. An ADR earns its
place only by recording a decision **with the rationale and rejected alternatives** a
future reader would otherwise re-derive — if there is nothing to re-derive, a line in one
of the files above is enough. The measure of the ADR set is not completeness but that
every entry is worth reading.

## Lifecycle

`Status`: `Proposed` → `Accepted` → `Superseded by ADR-NNNN` / `Deprecated`.

Strict ADR doctrine is append-only: never delete, always supersede. This repo deliberately
relaxes that, because append-only assumes every recorded decision had consequences worth
preserving — an assumption that fails for decisions recorded before they stabilized, and
the resulting tombstones bury the durable decisions readers came for:

- **Squash provisional supersessions.** Keep a `Superseded` tombstone only if the old
  decision had real-world consequences (it shipped, or another ADR / design / code depends
  on it). If it was provisional — reverted before shipping, nothing depending on it —
  overwrite or delete it instead. When in doubt, keep the tombstone.
- A `Proposed` ADR dropped before acceptance is deleted, not tombstoned.
- A decision that merely **moves** (absorbed into this README or another ADR) is deleted
  at its old path once every reference is repointed; no tombstone, since the decision
  itself still stands.
- A Template ADR's number is retired with the file and never reused. App ADRs may close
  gaps instead (see Renumbering below).

## Adopting template ADR updates (in a derived project)

After `git pull template main`, resolve conflicts with `/merge-template`, then reconcile ADRs:

1. Take Template ADRs (`docs/adr/template/**`) and this README from the template side.
2. Remove any old Template ADRs the template has deleted or absorbed elsewhere (e.g. a
   former lifecycle TADR now living in this README) if git did not remove them.
3. Repoint stale references in your own docs/code: a dropped Template ADR is gone; the
   development-workflow decision is `TADR-0001`, ADR policy is this README.
4. Leave your own App ADRs (`docs/adr/NNNN-*.md`) untouched — renumber only if a gap
   appeared (see below).

## Renumbering App ADRs

Compact only when a gap appears (e.g. a removed Template ADR freed `0001`). Renumbering
breaks existing references, so do it early and rarely.

1. Rename files to close the gap: `0002-foo.md` → `0001-foo.md`, …
2. Update every cross-reference (other ADRs, docs, code).
3. Never renumber Template ADRs.

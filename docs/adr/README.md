# Architecture Decision Records

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

## When to write an ADR, and its lifecycle

ADRs record **durable decisions**, not in-flight specs. The canonical spec is test code
([`TADR-0001`](template/0001-development-workflow.md)); a rapidly-changing spec lives in
tests + `CONTEXT.md`, which already keep only the current state because old tests are
rewritten when behavior changes. Author or update an ADR only once a decision is
**durable** — it has shipped, survived at least one iteration, or other work depends on it.
A direction expected to flip is not yet a decision; keep it out of the ADR set.

`Status` lifecycle: `Proposed` → `Accepted` → `Superseded by ADR-NNNN` / `Deprecated`.

When a decision is superseded, **squash provisional ones**: keep a `Superseded` tombstone
only if the old decision had real-world consequences (it shipped, or another ADR / design /
code depends on it). If it was provisional — reverted before shipping, nothing depending on
it — overwrite or delete it instead of leaving a tombstone. A `Proposed` ADR dropped before
acceptance is deleted, not tombstoned. This deliberately relaxes pure append-only where it
would only add noise; see [`TADR-0005`](template/0005-adr-lifecycle.md).

## Adopting template ADR updates (in a derived project)

After `git pull template main`, resolve conflicts with `/merge-template`, then reconcile ADRs:

1. Take Template ADRs (`docs/adr/template/**`) and this README from the template side.
2. Remove any old flat Template ADRs the template has deleted (e.g. a former
   `docs/adr/0001-*.md` now living under `template/`) if git did not remove them.
3. Repoint stale references in your own docs/code: a dropped Template ADR is gone; the
   development-workflow decision is now `TADR-0001`.
4. Leave your own App ADRs (`docs/adr/NNNN-*.md`) untouched — renumber only if a gap
   appeared (see below).

## Renumbering App ADRs

Compact only when a gap appears (e.g. a removed Template ADR freed `0001`). Renumbering
breaks existing references, so do it early and rarely.

1. Rename files to close the gap: `0002-foo.md` → `0001-foo.md`, …
2. Update every cross-reference (other ADRs, docs, code).
3. Never renumber Template ADRs.

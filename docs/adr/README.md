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

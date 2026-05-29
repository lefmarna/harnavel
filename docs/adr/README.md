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

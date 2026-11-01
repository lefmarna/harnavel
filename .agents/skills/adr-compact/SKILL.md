---
name: adr-compact
description: Compact ADRs down to the decisions that are currently in force. Remove or merge superseded decisions, temporary rollout notes, implementation tasks, and duplicate explanations, and renumber in-scope ADRs when needed. In derived repositories, compact only App ADRs; copied Template ADRs are read-only context and never compaction targets.
---

# ADR Compact

Compact ADRs to the durable decisions, rationale, and rejected alternatives currently in force.

## Preflight

1. Read `AGENTS.md`, `docs/adr/README.md`, and `GLOSSARY.md`.
2. Determine repository ownership before opening ADR files by checking `git remote get-url template`
   and `git remote get-url origin`:
   - **Derived app** (`template` remote exists): scope strictly to App ADRs
     (`find docs/adr -maxdepth 1 -type f -name '[0-9][0-9][0-9][0-9]-*.md'`). Never compact or edit
     `docs/adr/template/**` (read them only when needed to understand an App ADR's dependency or
     detect duplication). If the request targets a `TADR`, stop and direct it to the template
     repository.
   - **Template source** (no `template` remote and `origin` is the template repository, or the
     user confirms it): `docs/adr/template/NNNN-*.md` is in scope.
   - **Anything else**: App ADRs only.
3. Read the in-scope ADRs and the files they reference.

## Compaction Criteria

- **Keep**: active decisions, context required to avoid re-deriving them, realistic rejected
  alternatives, and durable constraints.
- **Remove**: superseded decisions (fold into the current decision without amendment history),
  rollout notes/plans/TODOs, implementation details already clear from code or tests, operational
  rules duplicated from `AGENTS.md`/`docs/`/skills, speculative future work, and long code blocks.
- **Delete or merge**: delete Proposed ADRs never adopted and ADRs superseded by a newer one (leave
  a short `Superseded by ADR-NNNN` tombstone only when external references still need it; never for
  a simple move). Renumber contiguous in-scope ADRs and update all references in the same change.

## Procedure

1. Inventory in-scope ADR files and confirm each active decision can be stated in one sentence.
2. Rewrite each ADR in present tense stating the decision, accepted trade-offs, and reconsidered
   alternatives.
3. Update references after any deletion, merge, or renumbering (`rg -n "ADR[- ]?NNNN|..."`; in a
   derived app add `--glob '!docs/adr/template/**'`).
4. Verify with `pnpm lint:docs`, `git diff --check`, `git diff --stat`, and `git diff` (in a
   derived app exclude `:(exclude)docs/adr/template/**`; if `pnpm lint:docs` fails inside
   `docs/adr/template/**`, report it as an upstream issue without editing those files).

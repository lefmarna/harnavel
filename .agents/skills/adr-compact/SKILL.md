---
name: adr-compact
description: Compact ADRs down to the decisions that are currently in force. Remove or merge superseded decisions, temporary rollout notes, implementation tasks, and duplicate explanations, and renumber in-scope ADRs when needed. In derived repositories, compact only App ADRs; copied Template ADRs are read-only context and never compaction targets.
---

# ADR Compact

Return ADRs to only the durable decisions that prevent future readers from having to decide again. This is not historical archiving; it is cleanup that keeps the currently valid decisions, rationale, and rejected alternatives readable.

## Preflight

- Read `AGENTS.md`, `docs/adr/README.md`, and `CONTEXT.md`.
- Determine repository ownership before reading any ADR body:
  - Run `git remote get-url template` and `git remote get-url origin` without opening ADR files.
  - If a `template` Git remote exists, treat the repository as a derived app. Scope the entire run
    to App ADRs (`docs/adr/NNNN-*.md`). Do not inventory, compact, edit, or report copied Template
    ADRs as compaction candidates; they are upstream-owned. If the request names a TADR, stop and
    direct that work to the template source repository.
  - Treat Template ADRs (`docs/adr/template/NNNN-*.md`) as eligible only when the current repository
    is explicitly the template source (its `origin` identifies the template repository, or the user
    explicitly confirms it). Renumber them after removal so the sequence stays contiguous, updating
    every reference in the same change.
  - In any other repository, default to App ADRs only.
- Read the in-scope target ADRs and the docs, tests, code, and ADRs they reference. In a derived
  app, copied Template ADRs may be read only to understand an App ADR's dependency, detect
  duplication, or validate links. Do not copy their content into an App ADR or propose changes to
  them from the derived repository.
- If deleting or merging in-scope ADRs creates gaps, numbers may be compacted as long as references
  are updated at the same time.

## Compaction Criteria

Keep:

- Decisions that are currently in force.
- Context needed to avoid deriving the decision again.
- Rejected alternatives that were actually considered and are likely to come up again.
- Consequences and constraints that must be preserved when changing the system.

Remove:

- Superseded decisions. Absorb them into the current decision and do not leave old decision text or amendment notes.
- Temporary rollout notes, rollout procedures, phase implementation plans, work logs, and TODOs.
- Fine implementation explanations where code or tests are now canonical.
- Operational explanations duplicated from `AGENTS.md`, `docs/`, skills, or tests.
- Speculative future work. If the project is not adopting it now, state only that it is out of scope.
- Long code examples. Link to nearby implementation files or tests when needed.

## Delete or Merge Decisions

- If a newer ADR fully supersedes the same decision, delete the old ADR or merge it into the new ADR.
- If an old decision has shipped and is still needed to understand other ADRs, docs, or code, leave a short `Superseded by ADR-NNNN` tombstone.
- Delete ADRs that stayed Proposed and were never adopted.
- Do not leave a tombstone for a simple move. Retarget references to the new location and delete the old file.

## Procedure

1. Inventory.
   - Build an explicit file list for the ownership scope first. In a derived app, use only the flat
     `docs/adr/NNNN-*.md` files; never use recursive `docs/adr/**` globs that include Template ADRs.
     List them with
     `find docs/adr -maxdepth 1 -type f -name '[0-9][0-9][0-9][0-9]-*.md' -print`.
   - Run line counts and keyword searches against that explicit list.
2. For each target ADR, confirm that the currently valid decision can be stated in one sentence.
3. Compact.
   - Keep only the background needed for the decision in `Context`.
   - Write `Decision` in the present tense.
   - Keep only alternatives that are likely to be reconsidered in `Considered alternatives`.
   - Keep only current constraints and risks in `Consequences`.
4. If deleting or merging ADRs, update references.
   - In the template source, search normally:
     `rg -n "ADR[- ]?NNNN|TADR[- ]?NNNN|deleted-file-slug|old heading" .`.
   - In a derived app, exclude copied Template ADRs from every search:
     `rg -n "ADR[- ]?NNNN|deleted-file-slug|old heading" --glob '!docs/adr/template/**' .`.
   - If compacting ADR numbers, update filenames and all references together.
5. Verify.
   - Run `pnpm lint:docs` in every repository so App ADR renumbering cannot leave broken references.
     In a derived app, do not fix a failure originating in `docs/adr/template/**`; record it as an
     upstream validation issue and stop rather than treating the compaction as complete.
   - In the template source, run `git diff --check`. In a derived app, run
     `git diff --check -- . ':(exclude)docs/adr/template/**'`.
   - In the template source, run `git diff --stat`. In a derived app, run
     `git diff --stat -- . ':(exclude)docs/adr/template/**'`.
   - In the template source, review `git diff`. In a derived app, review with
     `git diff -- . ':(exclude)docs/adr/template/**'`. Confirm the meaning of in-scope decisions did
     not change and old decision amendments were not left behind.

## Done When

- ADRs explain only decisions that are currently in force.
- Superseded decisions and temporary rollout concerns are gone.
- Links and references still work after deletion, merging, or number compaction.
- `pnpm lint:docs` passes. A copied Template ADR failure is reported upstream and leaves the run
  incomplete without editing that file locally.

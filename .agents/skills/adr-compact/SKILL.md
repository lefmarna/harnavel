---
name: adr-compact
description: Compact ADRs down to the decisions that are currently in force. Remove or merge superseded decisions, temporary rollout notes, implementation tasks, and duplicate explanations, and renumber App ADRs when needed.
---

# ADR Compact

Return ADRs to only the durable decisions that prevent future readers from having to decide again. This is not historical archiving; it is cleanup that keeps the currently valid decisions, rationale, and rejected alternatives readable.

## Preflight

- Read `AGENTS.md`, `docs/adr/README.md`, and `CONTEXT.md`.
- Read the target ADRs and the ADRs, docs, tests, and code they reference.
- Do not mix up Template ADRs and App ADRs.
  - Template ADRs: `docs/adr/template/NNNN-*.md`. Owned by the template. Do not reuse numbers.
  - App ADRs: `docs/adr/NNNN-*.md`. Owned by each app. If deletion or merging creates gaps, numbers may be compacted as long as references are updated at the same time.

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
   - `wc -l docs/adr/README.md docs/adr/**/*.md`
   - `rg -n "Amended|Superseded|Deprecated|Phase|rollout|temporary|transient|TODO|future|later|deferred|一時|展開|覆" docs/adr`
2. For each target ADR, confirm that the currently valid decision can be stated in one sentence.
3. Compact.
   - Keep only the background needed for the decision in `Context`.
   - Write `Decision` in the present tense.
   - Keep only alternatives that are likely to be reconsidered in `Considered alternatives`.
   - Keep only current constraints and risks in `Consequences`.
4. If deleting or merging ADRs, update references.
   - `rg -n "ADR[- ]?NNNN|TADR[- ]?NNNN|deleted-file-slug|old heading" .`
   - If compacting App ADR numbers, update filenames and all references together.
   - Do not compact Template ADR numbers.
5. Verify.
   - `pnpm lint:docs`
   - `git diff --stat`
   - Review `git diff` and confirm the meaning of decisions did not change and old decision amendments were not left behind.

## Done When

- ADRs explain only decisions that are currently in force.
- Superseded decisions and temporary rollout concerns are gone.
- Links and references still work after deletion, merging, or number compaction.
- `pnpm lint:docs` passes.

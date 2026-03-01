# Planning Guide

## Scope

Use this file for plan-mode work and every file created under `docs/plans/`.

## File Rules

- Location: `docs/plans/`
- Filename: `YYYY-MM-DD-short-topic.md`
- Language: Japanese or English
- Keep plans concise, scannable, and linked to the source-of-truth docs instead of copying long rules

## Required Sections

Every plan file must include these sections, even when the plan is short:

1. `Intent` - why the work is necessary now, what problem it solves, and what risk or cost exists if it is skipped
2. `Scope` - what is included and what is intentionally out of scope
3. `Options Considered` - the realistic approaches that were evaluated
4. `Selection Rationale` - why the chosen approach was selected over the alternatives
5. `Execution Steps` - the ordered implementation or investigation steps
6. `Verification` - how the outcome will be checked

## Optional Sections

- Add `Risks`, `Dependencies`, or `Open Questions` only when they materially affect execution.

## Notes

- Prefer links to `.kiro/specs/` over repeating detailed design.

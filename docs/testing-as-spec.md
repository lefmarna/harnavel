# Testing as specification

Tests are the canonical behavioral spec. The reviewer approves the **test cases**
and confirms they are green, without reading the implementation.

Decision, rationale, and risks: [TADR-0001](adr/template/0001-development-workflow.md).
This file is the operational playbook — keep it terse.

## Loop

1. **Intent** — one line of what should change (optionally preceded by Kiro
   `requirements`/`design` when useful).
2. **Spec** — author `it('...')->todo()` cases: the `describe`/`it` tree + assertion sketch.
3. **Approve** — reviewer approves the test cases **before** implementing. *Review happens here.*
4. **Implement** — red → green (`tdd` skill).
5. **Gates** — every CI check currently wired in this repo must pass. The target set
   across the TADR-0001 rollout is: green + coverage floor + `arch()` + Larastan +
   (scoped) Infection. Require only the checks already enabled.
6. **Merge** — approved spec × green × gates → merge without reading the implementation.
7. **Organize** — update only ADRs / `CONTEXT.md`. Don't reconstruct Kiro
   `requirements`/`design` after the fact (using them upfront is fine).

## Rules

- **Canonical layer:** Pest Feature (server logic, validation, authorization, Inertia
  component + props). Push behavior here.
- **E2E (Playwright):** smoke per new page + a few critical happy paths. Pure
  client-side React interaction is **out of scope** of the spec.
- **Readers:** engineers + AI agents. No generated spec docs.
- **Structure:** `describe('feature') → describe('scenario') → it('case')`. Japanese
  titles — success `〜できる`, conditional `〜の場合〜になる`. One `it` = one behavior.
  Approved-but-unimplemented = `it('...')->todo()`.
- **Coverage contract:** a behavioral change MUST have a test ("untested = unspecified").
  Exempt: styling/copy/layout, config/infra, docs. A new page MUST have a smoke E2E.
- **Coverage gate:** floor ~90% on `app/` (exclude boilerplate); 100% aspirational on
  `Services`/`Actions`/`Repositories`. No global 100%. Honesty is backed by the
  Infection mutation score, not by line coverage.

## Reviewing without reading the implementation

1. Read the `describe`/`it` tree as the spec outline.
2. Read the assertions — confirm they verify real behavior (`assertDatabaseHas`, real
   Inertia props), not tautologies.
3. Confirm CI is green and the gates passed.
4. Wrong behavior in the tree → fix the spec / file a bug. Never accept by reading the
   implementation instead.

## Migrating an existing suite (hybrid)

1. **Structural reshape** — flat `test()` → `describe`/`it` + naming, **preserve the
   assertions** (low risk).
2. **Read the tree as a spec** — suspicious behavior becomes a **separate bug ticket**;
   do not silently rewrite assertions, or you canonize the current bug as the spec.
3. **Sequence the gate** — non-blocking during the sweep, promote to blocking when the
   area is done.

## Propagating to derived projects

`git pull template main` + `/merge-template` (these files merge to the template side) →
run the migration → promote the gates. Phase 1 changes no CI, so the pull will not break
a derived pipeline.

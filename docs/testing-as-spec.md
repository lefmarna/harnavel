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
   (TADR-0001 Gates) is: green + coverage floor + `arch()` + Larastan + scoped mutation
   testing. Require only the checks already enabled.
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
  Exempt: styling/copy/layout, config/infra, docs. A new page MUST have a smoke E2E and a
  Feature spec that asserts its Inertia component and page-specific props shape.
- **Coverage gate:** floor ~90% on `app/` (exclude boilerplate); 100% aspirational on
  `Services`/`Actions`/`Repositories`. No global 100%. Honesty is backed by the
  mutation score (`pest --mutate`), not by line coverage.

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

## Mutation & coverage annotations

- **Prefer narrow `@pest-mutate-ignore` comments.** Put the directive on the mutated line or as a
  docblock/leading comment for non-executable declarations. Scope it to specific mutators with a
  comma-separated list: `// @pest-mutate-ignore: IncrementInteger,DecrementInteger`; no suffix
  ignores the whole node and its children. Put the reason on a separate plain comment line.
- **Re-measure suspicious survivors with `mise run mutate -- --no-cache`** — it bypasses the
  result cache and deletes nothing. Avoid `--clear-cache` and `rm -rf`ing the plugin's `.temp/`:
  depending on plugin version, either can remove `.temp/mutations/`, which the runner writes
  mutated sources into with no `mkdir`. If you must wipe, recreate it in the same breath:
  `rm -rf vendor/pestphp/pest-plugin-mutate/.temp && mkdir -p vendor/pestphp/pest-plugin-mutate/.temp/mutations`.
- **A `file_put_contents(... .temp/mutations/...): Failed to open stream` warning invalidates the
  score.** With that directory missing, mutants are never applied and surviving mutants are silently
  counted as killed — a *false* `Score: 100%` next to a green exit code (verified on v4.0.1: a real
  72.73% printed as 100%). Score and exit code both look clean, so the warning is the only tell —
  neither line alone is the verdict. Restore the directory (`composer reinstall pestphp/pest-plugin-mutate`,
  or the `mkdir` above) and re-measure. CI is unaffected: the v4.0.1 dist ships the empty
  `.temp/mutations/`, the mutation workflow `mkdir -p`s it before running as a version-drift guard,
  and CI never passes `--clear-cache`.
- **One DB-backed test process at a time** (see `commands.md`): never run `--mutate` while another
  `pest`/`--dirty` run shares the test schema.
- **Classify survivors honestly.** Defensive guards real input can't reach → `@codeCoverageIgnore`
  (line-level or start/end block + separate reason). Equivalent or non-deterministic mutants
  (elapsed-time math, array-key reindex, no-op empty-string guards) → `@pest-mutate-ignore`
  (+ separate reason). Never kill a mutant by weakening or deleting a test.
- **Prefer eliminating an equivalent mutant to annotating it — when elimination is free.** If the
  survivor sits on genuinely redundant code (a condition a preceding guard already makes
  unreachable, a duplicated check), delete the code: no mutant, less to read. Do **not** remove a
  construct that types or static analysis depend on — a `(string)`/`(int)`/`(bool)` cast over a
  `mixed` source still satisfies Larastan even when it is a runtime no-op, so it stays and gets
  annotated.
- **Annotate the load-bearing-but-unobservable survivors** (reason on a separate line). The
  recurring ones across projects: `lockForUpdate()` / `Log::*` (`RemoveMethodCall` /
  `RemoveFunctionCall` — unobservable in a single-process test), bounded retry guards
  `$i < self::MAX` (`SmallerToSmallerOrEqual` — the loop terminates identically), behaviour-neutral
  magic numbers (`IncrementInteger` / `DecrementInteger`), idempotent `array_values(array_filter(...))`
  (`UnwrapArrayValues` / `UnwrapArrayFilter`), defensive `... ?? ''` defaults (`EmptyStringToNotEmpty`),
  static-analysis casts over already-normalized config or mixed values (`RemoveBooleanCast` and
  related cast removals), early returns that only skip an equivalent fallback path
  (`RemoveEarlyReturn`),
  and eager-load or default-attribute arrays `->with([...])` / `['count' => 0]` (`RemoveArrayItem`).
- **A lone, clearly-equivalent survivor may be left un-annotated** when the directive would add more
  noise than the single survivor it silences — the calibrated `--min` floor leaves room for a small,
  honest gap. Keep these rare and self-evident.

## Test-writing gotchas

- **`Mail::fake()` drops recipient names set through `Mail::to($address, $name)`.** `MailFake::to()`
  accepts only the recipient value, so a `hasTo($address, $name)` callback will not match that
  name. That callback works only when the `Mailable` itself carries the named recipient. For
  facade-level recipient names, send through the `array` mailer and read
  `Mail::mailer()->getSymfonyTransport()->messages()`.
- **Unit tests don't boot the app.** Instantiating Eloquent models is fine, but `forceFill`-ing a
  `datetime`-cast attribute needs the connection resolver — put such value-object tests in `Feature`.

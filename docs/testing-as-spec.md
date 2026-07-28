# Testing as specification

Tests are the canonical behavioral spec. The reviewer approves the **test cases** and confirms they
are green. For routine low-risk changes that is the whole review; for the areas TADR-0001 lists as
review-required — auth, secrets, CI/delivery, migrations, concurrency, outbound side effects, and
the gate machinery itself — the implementation is read as well.

Decision, rationale, and risks: [TADR-0001](adr/template/0001-development-workflow.md).
This file is the operational playbook — keep it terse.

## Loop

1. **Intent** — one line of what should change (optionally preceded by Kiro
   `requirements`/`design` when useful).
2. **Spec** — author `it('...')->todo()` cases: the `describe`/`it` tree + assertion sketch.
   Behavior-preserving refactors skip this; the existing suite staying green is their spec.
3. **Approve** — reviewer approves the test cases **before** implementing. *Review starts here.*
4. **Implement** — red → green (`tdd` skill).
5. **Gates** — every CI check currently wired in this repo must pass. The target gate set defined
   by TADR-0001 is: green + coverage floor + `arch()` + Larastan + scoped mutation testing. Require
   only the checks already enabled.
6. **Merge** — approved spec × green × gates. Reading the implementation is skipped only for
   routine low-risk changes; see *Reviewing* below.
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
- **Coverage gate:** floor ~90% on `app/` (exclude boilerplate). No global 100%. Coverage measures
  how much of the code the written cases reach; it is an input to review, not a verdict
  (TADR-0001).

## Reviewing

1. Read the `describe`/`it` tree as the spec outline.
2. Read the assertions — confirm they verify real behavior (`assertDatabaseHas`, real
   Inertia props), not tautologies.
3. Confirm CI is green and the gates passed.
4. Wrong behavior in the tree → fix the spec / file a bug, rather than accepting it because the
   implementation looks reasonable.
5. Read the implementation too when the change touches a TADR-0001 review-required area (auth,
   secrets, CI/delivery, migrations, concurrency, outbound side effects, the gates themselves), or
   when it is wide, unfamiliar, or hard to reverse.

## Migrating an existing suite (hybrid)

1. **Structural reshape** — flat `test()` → `describe`/`it` + naming, **preserve the
   assertions** (low risk).
2. **Read the tree as a spec** — suspicious behavior becomes a **separate bug ticket**;
   do not silently rewrite assertions, or you canonize the current bug as the spec.
3. **Sequence the gate** — non-blocking during the sweep, promote to blocking when the
   area is done.

## Mutation testing

- **The allowlist lives in the specs.** A class is mutated because a spec declares
  `mutates(Foo::class)` at the top of the file — there is no `--class=` namespace list. A class
  earns a place only if it is **deterministic** and its spec **observes every branch**. Classes
  carrying DB, HTTP, Mail, Log, Cache, Auth, or a Repository do not qualify; whatever value
  mutating them has is not worth the annotations it pushes into production code.
- **This template's floor is 100% (`--min=100`, set in `mutation.yml`)** — the criterion restated:
  a qualifying class has no equivalent or unobservable mutant to excuse. A survivor means the spec
  is weak or the class does not belong on the list. Fix one or the other; never annotate around it,
  and never kill a mutant by weakening a test. The gate scores few classes by design; what it does
  not score is still covered by Feature specs and the coverage floor.
- **`@pest-mutate-ignore` should not appear in this repository.** Needing one is the signal that a
  class fails the allowlist criterion. `@codeCoverageIgnore` is separate and still applies: it
  serves the coverage floor, which covers all of `app/`.
- The **criterion** is shared surface; the **allowlist and `--min`** stay app-owned
  ([TADR-0003](adr/template/0003-shared-surface-convergence.md)). An application still selecting by
  `--class` keeps its own selection and threshold until it has built its allowlist and re-measured.
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

## Test-writing gotchas

- **`Mail::fake()` drops recipient names set through `Mail::to($address, $name)`.** `MailFake::to()`
  accepts only the recipient value, so a `hasTo($address, $name)` callback will not match that
  name. That callback works only when the `Mailable` itself carries the named recipient. For
  facade-level recipient names, send through the `array` mailer and read
  `Mail::mailer()->getSymfonyTransport()->messages()`.
- **Unit tests don't boot the app.** Instantiating Eloquent models is fine, but `forceFill`-ing a
  `datetime`-cast attribute needs the connection resolver — put such value-object tests in `Feature`.

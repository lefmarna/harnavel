# Testing as specification

Tests are the canonical behavioral specification. Write them before implementation and normally
continue through implementation and verification; stop after the tests only when the user asks.
Clarify ambiguous behavior before encoding it.

## Workflow

1. State the intended behavior.
2. Add the smallest test that specifies it; bug fixes begin with a reproduction.
3. Implement until the test passes.
4. Run the relevant static, unit, Feature, E2E, coverage, and mutation gates.
5. Keep only durable decisions or domain terms in documentation.

## Scope

- Pest Feature tests specify server behavior, validation, authorization, and Inertia component and
  props contracts.
- Playwright provides page smoke coverage and a few critical user flows. Use normal application
  routes, never E2E-only HTTP helpers.
- Test titles are Japanese in a `describe` / `it` hierarchy, with one behavior per `it`.
- A behavioral change needs a test. Styling, copy, configuration, infrastructure, and documentation
  changes use their relevant checks instead.
- A new page needs a Feature spec and a smoke E2E test.
- Coverage has a project-calibrated floor; it measures reach, not correctness.

Review the test cases and assertions first. Also review implementation for authentication, secrets,
CI and delivery, migrations, concurrency, outbound side effects, gate changes, or any change that is
wide, unfamiliar, or hard to reverse.

## Mutation testing

- Specs opt classes in with `mutates()`, and the selected set must score 100%.
- Evaluate every new or changed application-logic class. Add deterministic logic whose meaningful
  branches are observable through public behavior; thin framework wiring and external-I/O
  orchestration usually do not qualify.
- A survivor first indicates a missing assertion or a class that should be simplified. Before
  deleting its branch, identify a supported input or caller that reaches it: add the missing test
  when one exists, and simplify only when no supported behavior can observe it. Do not add
  mutation-ignore annotations or weaken tests to make the score pass.
- Removing `mutates()` weakens the gate and requires explicit review.
- Run mutation testing separately from other DB-backed Pest processes. If a mutant blocks on row
  locks, shorten that spec's session lock wait so it fails within the mutation budget.

## Known test boundaries

- `Mail::fake()` does not preserve recipient names supplied through `Mail::to($address, $name)`.
- Unit tests do not boot Laravel; tests requiring model casts or a connection resolver belong in
  Feature tests.

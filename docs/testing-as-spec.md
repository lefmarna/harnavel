# Testing as specification

[TADR-0001](adr/template/0001-tests-as-specification.md) explains why tests specify behavior.
Direct review still covers security, migrations, concurrency, side effects, delivery, and gate changes.

## Responsibilities

- Pest Feature specs own server and Inertia contracts; Playwright owns browser rendering smoke and
  critical user flows.
- Every new user-visible Inertia page needs both; non-page endpoints need only relevant coverage.
- Test titles are Japanese.

## Mutation testing

- Opt deterministic application-logic classes with observable branches into mutation testing with
  `mutates()`; omit thin wiring and external-I/O orchestration.
- Treat a survivor first as a missing assertion or simplification signal. Strengthen the spec when
  it represents supported behavior; otherwise simplify the implementation.
- Do not weaken tests, add mutation ignores, or remove `mutates()` without user confirmation.

# Testing as specification

[TADR-0001](adr/template/0001-tests-as-specification.md) explains why tests specify behavior.
Direct review still covers security, migrations, concurrency, side effects, delivery, and gate changes.

## Responsibilities

- Before adding a test, identify the concrete defect it would catch beyond existing checks.
  Do not add tests that duplicate coverage or merely restate implementation.
- Pest Feature specs own server/Inertia contracts; Playwright owns browser smoke and critical flows.
- New user-visible Inertia pages need both; other endpoints need relevant coverage.
- Test titles are Japanese.
- A test that boots the app in a subprocess asserts its exit code, or its HTTP status when a kernel
  handles the request: a boot failure becomes a 500 the other assertions still accept. Booting as
  production also means passing every environment variable that production boot requires.

## Mutation testing

- Opt deterministic application-logic classes with observable branches into mutation testing with
  `mutates()`; omit thin wiring and external-I/O orchestration.
- Only tests in files that declare `mutates()` run against mutants, so a test that must kill a
  mutant lives in such a file; several files may declare the same class.
- A declaring file whose own tests never reach the class produces no mutants at all, and the run
  still passes. Re-check the mutant count when a screen a declaration depends on is replaced.
- For surviving mutations, strengthen assertions for supported behavior or simplify the implementation.
- Do not weaken tests, add mutation ignores, or remove `mutates()` without user confirmation.

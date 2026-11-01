# Testing as specification

See [TADR-0001](adr/template/0001-tests-as-specification.md). Direct review still covers security,
migrations, concurrency, side effects, delivery, and gate changes.

## Responsibilities

- Before adding a test, identify the concrete defect it would catch beyond existing checks; a test
  that duplicates coverage or restates implementation adds nothing.
- Pest Feature specs own server and Inertia contracts; Playwright owns browser smoke and critical
  flows. New user-visible Inertia pages need both; other endpoints need relevant coverage.
- Test titles are Japanese.
- A test that boots the app in a subprocess asserts its exit code, or its HTTP status when a kernel
  handles the request: a boot failure becomes a 500 the other assertions still accept.

## Mutation testing

- Opt deterministic application-logic classes with observable branches in with `mutates()`; omit
  thin wiring and external-I/O orchestration.
- Only tests in files that declare `mutates()` run against mutants, so a test that must kill a
  mutant lives in such a file; several files may declare the same class.
- A declaring file whose own tests never reach the class produces no mutants, and the run still
  passes. Re-check the mutant count when replacing a screen a declaration depends on.
- Kill surviving mutants by strengthening assertions for supported behavior or simplifying the
  implementation. Weakening tests, adding mutation ignores, or removing `mutates()` needs user
  confirmation.

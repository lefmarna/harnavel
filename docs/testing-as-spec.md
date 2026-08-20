# Testing as specification

Tests are the canonical specification of observable behavior; [TADR-0001](adr/template/0001-tests-as-specification.md)
records why the project does not maintain a separate behavioral design document. They do not
replace direct review of authentication, migrations, concurrency, external side effects, or gate
changes.

## Responsibilities

- Start a behavioral change by writing or adjusting a test that fails for the expected reason.
- Pest Feature specs own server and Inertia contracts; Playwright owns browser rendering smoke and
  critical user flows.
- Every new user-visible Inertia page has a Pest Feature spec and a Playwright smoke spec. Redirects,
  callbacks, APIs, and other non-page endpoints need only the tests appropriate to their behavior.
- Pest uses a `describe` / `it` hierarchy with one behavior per `it`; Pest and Playwright test titles
  are Japanese.
- Configuration, tooling, and CI wiring are not behavior; do not add tests or checks for them.
  TADR-0001 bounds the one exception, where a deploy failure would be both irreversible and silent.

## Mutation testing

- Opt deterministic application-logic classes with observable branches into mutation testing with
  `mutates()`; omit thin wiring and external-I/O orchestration.
- Treat a survivor first as a missing assertion or simplification signal. Strengthen the spec when
  it represents supported behavior; otherwise simplify the implementation.
- Do not weaken tests, add mutation ignores, or remove `mutates()` without user confirmation.

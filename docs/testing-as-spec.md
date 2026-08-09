# Testing as specification

Tests are the canonical behavioral specification; [TADR-0001](adr/template/0001-tests-as-specification.md)
records why the project does not maintain a separate behavioral design document.

## Conventions

- Pest Feature specs own server and Inertia contracts; Playwright covers page smoke and critical
  user flows.
- Test titles are Japanese in a `describe` / `it` hierarchy, with one behavior per `it`. No gate
  rejects an English title.

## Mutation testing

- Treat a survivor first as a missing assertion or simplification signal. Add a test when a
  supported input reaches it; simplify when no supported behavior can.
- Simplify static-analysis-only casts that leave equivalent survivors and scanner mutants that do
  not terminate. If that is not possible, explicitly review the class's `mutates()` eligibility.
- Do not add mutation ignores, weaken tests, or accept unbounded runs to pass the gate. Removing
  `mutates()` also needs explicit review because it silently shrinks the gate.
- Bound row-locking mutants by shortening that spec's session lock wait.

## Interactive browser verification

Start the app and Vite, then use Playwright CLI for transient checks. Replace `NGINX_PORT` with its
value from `.env`.

```bash
npx @playwright/cli open http://localhost:NGINX_PORT
npx @playwright/cli snapshot --depth=4
npx @playwright/cli screenshot
npx @playwright/cli close
```

Prefer snapshots for behavior and screenshots for visual output. Add an E2E spec when the behavior
should remain guarded.

# Testing as specification

`AGENTS.md` states the obligations and TADR-0001 records the decision. This file holds only what
neither of them carries and no gate can catch.

## Conventions

- Test titles are Japanese in a `describe` / `it` hierarchy, with one behavior per `it`. No gate
  rejects an English title.

## Mutation testing

- A survivor first indicates a missing assertion or a class that should be simplified. Before
  deleting its branch, identify a supported input or caller that reaches it: add the missing test
  when one exists, and simplify only when no supported behavior can observe it.
- When a static-analysis-only cast leaves an equivalent survivor or a scanner mutant becomes
  non-terminating, simplify the implementation first. If that is not possible, explicitly review
  the class's `mutates()` eligibility instead of adding an ignore, weakening a test, or accepting
  an unbounded mutation run.
- Do not add mutation-ignore annotations or weaken tests to make the score pass, and treat removing
  `mutates()` as a change that needs explicit review. All three shrink the gate instead of failing
  it, so the score stays green while the coverage it reports becomes meaningless.
- If a mutant blocks on row locks, shorten that spec's session lock wait so it fails within the
  mutation budget.

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

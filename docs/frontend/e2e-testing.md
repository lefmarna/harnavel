# E2E Test Design

## Framework

Playwright with 3 browser targets:

| Project  | Browser         | Viewport |
| -------- | --------------- | -------- |
| chromium | Desktop Chrome  | Default  |
| firefox  | Desktop Firefox | Default  |
| webkit   | Desktop Safari  | Default  |

CI runs `--project=chromium` only for speed. Full matrix on demand.

## Configuration

```typescript
// playwright.config.ts key settings
retries: isCI ? 2 : 0
workers: isCI ? 1 : undefined // sequential in CI
trace: 'retain-on-failure'
screenshot: 'only-on-failure'
video: 'retain-on-failure'
```

## Test Coverage

| Spec            | Scope                                |
| --------------- | ------------------------------------ |
| `smoke.spec.ts` | Page loads, titles, basic navigation |

## Test Patterns

**Setup function** (per-spec, not Page Object):

```typescript
const setupDashboard = async (page: Page) => {
  await page.goto('/dashboard')
  await page.waitForLoadState('networkidle')
  return page
}
```

**Selector priority**:

1. `getByRole('heading', { name: '...' })` - semantic (preferred)
2. `getByLabel('...')` - form elements
3. `locator('[data-testid="..."]')` - custom data attributes (fallback)
4. `locator('a[href="..."]')` - link by href (last resort)

**Async handling**: Always `waitForLoadState('networkidle')` after navigation. No `sleep()`.

## Commands

```bash
pnpm test:e2e                      # All browsers
pnpm test:e2e --project=chromium   # Chromium only (CI default)
pnpm test:e2e:ui                   # Interactive UI mode
pnpm test:e2e:headed               # Headed mode (visible browser)
```

## Verification Checklist

- [ ] Test titles in Japanese (consistency with backend tests)
- [ ] Semantic locators used (`getByRole`, `getByLabel`) over CSS selectors
- [ ] `waitForLoadState('networkidle')` after navigation (no `sleep()`)
- [ ] Responsive behavior tested (if applicable)
- [ ] New pages have at least a smoke test (loads, title correct)

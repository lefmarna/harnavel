# Test Strategy

## Test Layers

| Layer   | Tool       | Scope                             | Speed    | Location         |
| ------- | ---------- | --------------------------------- | -------- | ---------------- |
| Unit    | Pest       | Pure logic, helpers, calculations | Fast     | `tests/Unit/`    |
| Feature | Pest       | API endpoints, DB, auth flows     | Moderate | `tests/Feature/` |
| E2E     | Playwright | Full user flows (browser)         | Slow     | `e2e/tests/`     |

## TDD Workflow

1. Write a failing test: `php artisan make:test --pest FeatureName`
2. Implement minimal code to pass
3. Refactor while keeping tests green
4. Run `php artisan test --filter=FeatureName` frequently

## Pest Conventions

**Test names in Japanese** for readability in test output:

```php
test('ユーザーが新規登録できること', function () {
    // Arrange
    $user = User::factory()->make();

    // Act
    $response = $this->post('/register', $user->toArray());

    // Assert
    $response->assertOk();
});
```

**Base setup** (`tests/Pest.php`):

- All Feature tests use `RefreshDatabase` (DB reset per test)

**Patterns**:

- Use `Mail::fake()`, `Notification::fake()` for mail/notification assertions
- Use factories for test data (`User::factory()->create()`)
- Use datasets (`->with([...])`) for parameterized validation tests

## E2E Testing (Playwright)

Tests in `e2e/tests/`. Run against the Docker backend.

```bash
pnpm test:e2e                    # All tests
pnpm test:e2e --project=chromium # Chromium only (CI default)
pnpm test:e2e:ui                 # Interactive UI mode
```

**Guidelines**:

- Test titles in Japanese
- Semantic locators (`getByRole`, `getByLabel`) over CSS selectors
- Each test file self-contained (no shared mutable state)

## Verification Checklist

- [ ] **Naming**: Test names describe behavior in Japanese
- [ ] **Factory**: Used for test data (no manual model creation)
- [ ] **Mocking**: `Mail::fake()` / `Notification::fake()` for side effect assertions
- [ ] **Coverage**: Feature tests cover happy path + error cases + edge cases
- [ ] **Performance**: No `sleep()` in tests (use retrying assertions in E2E)
- [ ] **Isolation**: Tests pass independently (no order dependency)

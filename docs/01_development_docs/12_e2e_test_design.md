# E2E Test Design

This document defines the critical path scenarios and implementation details for Playwright tests.
It ensures that core user flows are always functional.

## 1. Test Scenarios (Gherkin Style)

### A. Authentication (Smoke Test)

```gherkin
Feature: User Login
  Scenario: Successful login
    Given the user is on the login page
    When they enter valid credentials (test@example.com / password)
    And click "Login"
    Then they should be redirected to the dashboard
    And see "Dashboard" heading
```

### B. User Registration

```gherkin
Feature: User Registration
  Scenario: Create new account
    Given the user is on the registration page
    When they enter valid name, email, and password
    And click "Register"
    Then they should be redirected to the dashboard
    And see "Welcome, {Name}"
```

### C. Critical Feature (Example: Checkout)

```gherkin
Feature: Checkout
  Scenario: Purchase item
    Given the user is logged in
    And has items in cart
    When they proceed to checkout
    And enter payment details
    Then they should see "Order Confirmed"
```

## 2. Implementation Guidelines (Playwright)

- **Fixtures**: Use `test.use({ storageState: 'auth.json' })` for authenticated tests.
- **Locators**: Use semantic locators (`getByRole('button', { name: 'Login' })`).
- **Assertions**: Use auto-retrying assertions (`expect(page).toHaveURL('/dashboard')`).
- **Parallelism**: Run tests in parallel (`fullyParallel: true`).
- **Retries**: Configure retries for flaky tests (`retries: 2`).

## 3. Directory Structure (`tests/e2e/`)

- `auth.setup.ts`: Global setup for authentication state.
- `smoke.spec.ts`: Core smoke tests (Login, Dashboard).
- `features/`: Feature-specific tests (Checkout, Profile).

## Verification Checklist

- [ ] **Smoke Test**: Does `pnpm test:e2e smoke` pass?
- [ ] **Critical Path**: Does `pnpm test:e2e checkout` pass?
- [ ] **Selectors**: Are locators stable (id, data-testid, role)?
- [ ] **Speed**: Do tests complete within 5 minutes (CI limit)?
- [ ] **Screenshots**: Are failure screenshots captured (`screenshot: 'only-on-failure'`)?
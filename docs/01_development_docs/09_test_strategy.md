# Test Strategy

This document defines the testing strategy for ensuring quality and reliability.
Use this guide when creating new features or fixing bugs.

## 1. Test Layers

### A. Unit Tests (Pest)
**Purpose**: Verify isolated logic.
- **Speed**: Very fast (<10ms).
- **Scope**: Domain objects, utility functions, single methods.
- **Dependencies**: Mocked (Mockery/Interfaces).
- **Command**: `php artisan test --filter Unit`

### B. Feature/Integration Tests (Pest)
**Purpose**: Verify component interactions (DB, API).
- **Speed**: Moderate.
- **Scope**: Controllers, Repositories, Jobs, Events.
- **Dependencies**: Real DB (SQLite/MariaDB), Fake external services (`Http::fake()`).
- **Command**: `php artisan test --filter Feature`

### C. E2E Tests (Playwright)
**Purpose**: Verify critical user flows.
- **Speed**: Slow.
- **Scope**: Full application stack (Login -> Checkout).
- **Dependencies**: Real environment.
- **Command**: `pnpm test:e2e`

## 2. Coverage Goals

- **Business Logic (Domain)**: >90%
- **Controllers**: >80% (Happy path + Error cases)
- **Critical Paths**: 100% (E2E)

## 3. Best Practices (Pest)

- **Datasets**: Use `with(...)` for parameterized tests.
- **Assertions**: Use expressive assertions (`expect($user)->toBeInstanceOf(User::class)`).
- **Factories**: Use Model Factories for test data setup.
- **Mocking**: Minimize mocking in Feature tests; maximize in Unit tests.

## Verification Checklist

- [ ] **Unit Tests**: Does logic handle edge cases (null, empty, limits)?
- [ ] **Feature Tests**: Does DB state change correctly? Are events fired?
- [ ] **E2E Tests**: Does the critical path work end-to-end?
- [ ] **Performance**: Do tests run efficiently (minimize sleep)?
- [ ] **Cleanup**: Does test data clean up after execution (`RefreshDatabase`)?
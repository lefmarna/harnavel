# Pest Testing Patterns

This document defines implementation patterns and best practices for Pest testing.
It ensures high-quality and readable tests.

## 1. Test Patterns (Arrange-Act-Assert)

### A. Unit Test

```php
test('it calculates the total correctly', function () {
    // Arrange
    $cart = new Cart();
    $cart->add(new Product('Apple', 100));

    // Act
    $total = $cart->total();

    // Assert
    expect($total)->toBe(100);
});
```

### B. Feature Test (HTTP)

```php
test('users can view the dashboard', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)->get('/dashboard');

    // Assert
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
        );
});
```

### C. Database Test (RefreshDatabase)

```php
uses(RefreshDatabase::class);

test('a user can be created', function () {
    // Act
    User::factory()->create(['email' => 'test@example.com']);

    // Assert
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});
```

## 2. Best Practices

- **Datasets**: Use `with(...)` for parameterized tests.
- **Mocking**: Use `mock(Service::class)->shouldReceive(...)` for external services.
- **Factories**: Use `User::factory()` for data setup.
- **Assertions**: Use expressive assertions (`expect($response)->toBeOk()`).

## Verification Checklist

- [ ] **Descriptive**: Do test names describe "what happens when..."?
- [ ] **Isolation**: Are tests independent (no shared state)?
- [ ] **Coverage**: Are all logic branches covered (Happy Path, Error, Edge Case)?
- [ ] **Speed**: Do tests execute quickly (< 10ms for Unit)?
- [ ] **Mocking**: Is external IO mocked (HTTP, File System)?
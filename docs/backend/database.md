# Database Design

MariaDB 10.5 via Eloquent. Full ER diagrams and table definitions: [schema-design-spec.md](schema-design-spec.md)

## Migration Gotcha (Laravel 12)

When modifying a column, the migration **must include ALL attributes** previously defined on the column. Laravel 12 drops any unspecified attributes.

```php
// Bad - will drop the default value
$table->string('name')->nullable()->change();

// Good - preserves all attributes
$table->string('name')->default('')->nullable()->change();
```

## Test Database

Separate `mariadb-testing` Docker container. Feature tests use `RefreshDatabase` trait with factories for consistent data.

## Verification Checklist

- [ ] **Migration**: Column modification includes ALL existing attributes
- [ ] **Factory**: Created/updated for new/modified models
- [ ] **Transaction**: `DB::transaction` wraps multi-step write operations
- [ ] **Eager loading**: Used (no N+1 patterns)
- [ ] **Query**: `Model::query()` used instead of `DB::` facade (except `DB::transaction`)

# Database Design Specification

Use this file for project-specific schema policy that is not obvious from Laravel defaults.
Runtime truth still lives in `database/migrations/` and `SHOW CREATE TABLE`.

## Naming

- Boolean columns use `is_` or `has_`

## Constraint and Index Names

- Primary key: implicit `PRIMARY`
- Regular index: `{table}_{column}_index`
- Unique index: `{table}_{columns}_unique`
- Foreign key constraint: `{table}_{column}_foreign`

## Key and Relation Policy

- New application domain tables use UUIDv7.
- Existing tables (including `users` and framework-managed/auth tables) keep current key types until an explicit migration plan exists
- Pure pivot tables may use composite keys when no dedicated Eloquent model is required
- Foreign keys must match the referenced primary key type exactly
- Choose `ON DELETE` by lifecycle:
  - `CASCADE`: child rows are meaningless without the parent
  - `SET NULL`: historical rows should survive parent deletion
  - `RESTRICT`: reference data must not be deleted while in use

## Index Policy

- Every table must have a primary key.
- Every foreign key column must have an index.
- Business uniqueness must be protected with unique indexes.
- Add indexes for high-frequency filter/sort columns.
- Avoid indexes that are not tied to concrete query patterns.
- Avoid indexing low-cardinality boolean columns alone unless query volume and selectivity justify it.

## Migration Checklist

1. Table/column names follow `snake_case` and domain vocabulary.
2. PK/FK types match referenced tables exactly (`uuid`, `bigint`, or composite policy as designed).
3. `ON DELETE` action is explicitly chosen and justified.
4. Indexes are tied to query patterns and FK/unique requirements.
5. Nullable columns are intentional.
6. Add comments for domain-specific columns when intent is not obvious.
7. `down()` is safe and reversible.
8. Run verification after migration changes.

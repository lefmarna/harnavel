# Database Design Specification (Naming, Policy & Checklist)

Primary source of schema truth: `database/migrations/` and runtime `SHOW CREATE TABLE`.

## 1. Naming Conventions

### 1.1 General

- Table names: `snake_case`, plural nouns (`users`, `sessions`)
- Column names: `snake_case`
- PK column name: `id`
- FK column name: `<referenced_singular>_id` (`user_id`)
- Timestamp suffix:
  - lifecycle timestamps: `_at` (`created_at`, `updated_at`)
- Boolean prefix:
  - `is_` / `has_`

### 1.2 Constraint/Index Naming

- Primary key: implicit `PRIMARY`
- Regular index: `{table}_{column}_index`
- Unique index: `{table}_{columns}_unique`
- Foreign key constraint: `{table}_{column}_foreign`

## 2. Data Type Policy

- Primary key strategy for new application domain models/tables: UUIDv7
  - Migration: `uuid('id')->primary()`
  - Model: `HasUuids` (Laravel 12 generates UUIDv7-compatible IDs)
- Existing tables (including `users` and framework-managed/auth tables) keep current key types until an explicit migration plan exists
- Pure pivot tables may use composite keys when no dedicated Eloquent model is required
- Foreign keys must match the referenced primary key type exactly
- Text:
  - bounded text: `varchar(n)`
  - unbounded/free-form: `text` / `longtext`
- Time:
  - current baseline: `timestamp`
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`

## 3. Index Policy

## 3.1 Required Indexes

- Every table must have a primary key.
- Every foreign key column must have an index.
- Business uniqueness must be protected with unique indexes.

## 3.2 Query-Driven Indexes

- Add indexes for high-frequency filter/sort columns.
- Avoid indexes that are not tied to concrete query patterns.
- Avoid indexing low-cardinality boolean columns alone unless query volume and selectivity justify it.

## 4. Relationship Constraint Policy

Choose `ON DELETE` action by data lifecycle:

- `CASCADE`: child rows have no meaning without parent
- `SET NULL`: historical/financial record should survive parent deletion
- `RESTRICT`: master/reference data must not be deleted while referenced

Additional rules:

- If a relation is modeled without FK (e.g., polymorphic or session linkage), document integrity ownership clearly at application layer.
- Non-null FK should be default unless business requirements explicitly allow orphaning.

## 5. Migration Design Checklist (For New Tables)

1. Table/column names follow `snake_case` and domain vocabulary.
2. PK/FK types match referenced tables exactly (`uuid`, `bigint`, or composite policy as designed).
3. `ON DELETE` action is explicitly chosen and justified.
4. Indexes are tied to query patterns and FK/unique requirements.
5. Nullable columns are intentional and explained in PR description.
6. Column comments are added for domain-specific fields.
7. Rollback (`down`) path is safe and reversible.
8. Verification runs successfully after migration updates.

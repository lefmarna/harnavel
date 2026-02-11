# Database Design

This document defines schema design rules and best practices for creating database tables.

## 1. Naming Conventions

- **Tables**: Plural `snake_case` (e.g., `user_profiles`).
- **Columns**: `snake_case`. Boolean uses `is_` or `has_` prefix.
- **Foreign Keys**: `resource_id` (e.g., `user_id` in `orders` table).
- **Indexes**: Describe index usage (e.g., `idx_user_email`).

## 2. Constraints & Integrity

- **Foreign Keys**: ALWAYS enforce at database level (`constrained()`).
  - Use `onDelete('cascade')` carefully. Prefer `restrict` for critical data.
- **Nullability**: Columns are NOT NULL by default unless explicitly `nullable()`.
- **Soft Deletes**: Use `deleted_at` only for recoverable entities (e.g., User, Project).

## 3. Migrations

- **Down Method**: ALWAYS implement rollback logic.
- **Atomicity**: Changes should be atomic. Do not mix schema changes and data seeding.
- **Type**: Use `ulid()` or `uuid()` for primary keys if distributed system scaling is expected (default to `id()`/BigInt for simplicity).

## Verification Checklist

- [ ] **Naming**: Are table/column names `snake_case`?
- [ ] **Indexes**: Are frequently queried columns indexed?
- [ ] **Foreign Keys**: Is `constrained()` used? Is `onDelete` behavior defined?
- [ ] **Down Method**: Can `migrate:rollback` run without errors?
- [ ] **Comment**: Is column purpose clear (use `->comment()` if needed)?
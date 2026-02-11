# Type Definitions

This document defines the TypeScript and PHP type system rules.
It enforces strict typing to prevent runtime errors and improve developer experience.

## 1. PHP (Backend)

- **Strict Types**: Always use `declare(strict_types=1);` in all PHP files.
- **Type Hints**:
  - Arguments: Always type-hint arguments (e.g., `User $user`).
  - Return Types: Always specify return types (e.g., `: Response`, `: void`).
  - Properties: Type-hint class properties (PHP 7.4+).
- **Generics**: Use PHPDoc for collections (`Collection<int, User>`).

## 2. TypeScript (Frontend)

- **Interfaces**: Define interfaces for all domain models (e.g., `User`, `Post`).
  - Use `export interface User { ... }` in `types/models.ts`.
- **Props**: Define strict interfaces for React component props.
  - Avoid `React.FC` (implicit children/props). Prefer explicit `Props` interface.
- **Avoid `any`**:
  - **Prohibited**: Using `any` type (unless absolutely necessary for 3rd-party libs).
  - **Allowed**: `unknown` (requires narrowing), `Record<string, unknown>`.

## 3. Inertia Props

- **Shared Data**: Define a global interface for shared props (User, Flash messages).
  - `types/inertia.d.ts` (augment `PageProps`).
- **Page Data**: Define specific props for each page component.

## Verification Checklist

- [ ] **PHPStan**: Run `vendor/bin/phpstan analyse` -> No errors (Level 5+).
- [ ] **TypeScript**: Run `tsc --noEmit` -> No errors.
- [ ] **No `any`**: Search for `any` in codebase (`grep -r "any" src/ resources/js/`).
- [ ] **Strict Types**: Check if `declare(strict_types=1);` exists in new PHP files.
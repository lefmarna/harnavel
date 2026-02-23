# Code Style

- No magic numbers; use named constants
- Use early returns to reduce nesting
- Keep nesting shallow (max 2-3 levels)

# Environment

- Use `pnpm` (not npm). Run `pnpm fix` after changes.

# Stack

React 19, TypeScript, Inertia v2, Shadcn UI, Radix UI, Tailwind v4.

# Structure

`resources/js/`: pages/ (Inertia pages), components/ (shared UI), layouts/, hooks/, lib/, actions/, routes/

# Naming

- Dirs/Files: kebab-case (`auth-wizard/`, `email-input.tsx`)
- Components: PascalCase (`EmailInput`)
- Hooks: `use` prefix (`useAppearance`)
- Use named exports

# TypeScript

**Prefer functional programming style:**

- Use `const` only; `let` is allowed only for `for` loop indices
  ```ts
  // Bad: let result = []; items.forEach(x => result.push(x.id))
  // Good: const result = items.map(x => x.id)
  ```
- Prefer `map`/`filter`/`reduce` over loops; use `forEach` only for side effects (API calls, DOM updates)
  ```ts
  // Bad: let total = 0; items.forEach(x => { total += x.price })
  // Good: const total = items.reduce((sum, x) => sum + x.price, 0)
  // Good: items.forEach(x => console.log(x))  // side effect is OK
  ```
- Prefer `switch` over `if-else` for discrete values; always include `default` with exhaustiveness check
  ```ts
  // Bad: if (status === 'pending') { ... } else if (status === 'done') { ... }
  // Good:
  switch (status) {
    case 'pending': return <Pending />
    case 'done': return <Done />
    default: const _exhaustive: never = status  // compile error if case missing
  }
  ```

**Type definitions:**

- Prefer `interface` over `type` for object shapes (better error messages, extendable)
  ```ts
  // Bad: type User = { name: string; email: string }
  // Good: interface User { name: string; email: string }
  ```
- Avoid enums; use `as const` objects
  ```ts
  // Bad: enum Status { Pending, Done }
  // Good: const STATUS = { PENDING: 'pending', DONE: 'done' } as const
  ```
- Avoid type assertions (`as`); design for type inference or use type narrowing
  ```ts
  // Bad: const user = data as User
  // Good: if (data && typeof data === 'object' && 'email' in data) { /* data is narrowed */ }
  ```

# useEffect Policy

useEffect is **only** for external sync (API, WebSocket, browser APIs, timers).

**Anti-patterns:**

- Copying props/derived values into local state
- Running logic in response to flag changes
- Handling user actions in effects
- Updating derived or validation states within effects
- One-time initialization with empty dependency array (use lazy state initializer `useState(() => init())` or module-level initialization instead)

**Principles:**

- Derive values during render
- Handle user actions in event handlers
- Keep effects only for real side effects
- Add comment explaining external resource being synced

# Tailwind v4

**Always use Tailwind v4 class names** (not v3 deprecated utilities).

| v3 (Don't use)      | v4 (Use this)   |
| ------------------- | --------------- |
| `bg-opacity-50`     | `bg-black/50`   |
| `text-opacity-*`    | `text-black/50` |
| `flex-shrink-0`     | `shrink-0`      |
| `flex-grow`         | `grow`          |
| `overflow-ellipsis` | `text-ellipsis` |

# UI & Styling

- Use Shadcn UI, Radix, and Tailwind for components and styling
- Mobile-first responsive design
- Component design rules (margins, spacing, placement): [components.md](components.md)

# Tooling

`pnpm dev` / `pnpm build` / `pnpm lint`

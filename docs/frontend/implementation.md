# Code Style

- No magic numbers; use named constants
- Use early returns to reduce nesting
- Keep nesting shallow (max 2-3 levels)
- Test function names in Japanese for readability

# Environment

- Use `pnpm` (not npm). Run `pnpm fix` after changes.
- See `docs/backend/coding_rules.md` for Laravel Boost rules.

# Stack

React 19, TypeScript, Inertia v2, Tailwind v4, Radix UI, Lucide, Wayfinder.

# Structure

```
resources/js/
├── pages/          # Inertia pages
├── components/ui/  # Radix-based UI components
├── layouts/        # Layout components
├── hooks/          # Custom hooks
├── lib/            # Utilities
├── actions/        # Wayfinder (auto)
└── routes/         # Wayfinder (auto)
```

# Naming

- Dirs/Files: kebab-case (`auth-wizard/`, `email-input.tsx`)
- Components: PascalCase (`EmailInput`)
- Hooks: `use` prefix (`useAppearance`)
- Use named exports

# TypeScript

**Prefer functional programming style:**

- Use `const` only; `let` is allowed only for `for` loop indices (exceptions: performance-critical or sequential processing where mutation is necessary)
- Prefer `map` for transformations, `filter` for filtering, `reduce` for aggregations; use `forEach` only when side effects are needed
- Prefer `switch` over `if-else` when branching on discrete values; always include `default` (use `never` for exhaustiveness), no fallthrough

**Type definitions:**

- Prefer `interface` over `type`
- Avoid enums; use `as const` or maps
- Avoid type assertions (`as`) and type guards (`is`); use `instanceof` or design for type inference

# Navigation & Forms

Use Inertia `<Link>` / `router.visit()` with Wayfinder:

```tsx
import { show } from '@/actions/App/Http/Controllers/UserController'
;<Link {...show.link(id)}>View</Link>
```

Forms with Wayfinder:

```tsx
<Form {...store.form()}>{({ errors, processing }) => (...)}</Form>
```

# useEffect Policy

useEffect is **only** for external sync (API, WebSocket, browser APIs, timers).

**Anti-patterns:**

- Copying props/derived values into local state
- Running logic in response to flag changes
- Handling user actions in effects
- Updating derived or validation states within effects
- One-time initialization with empty dependency array (use useMemo instead)

**Principles:**

- Derive values during render
- Handle user actions in event handlers
- Keep effects only for real side effects
- Add comment explaining external resource being synced

# Tailwind v4

**Always use Tailwind v4 class names** (not v3 deprecated utilities).

- CSS-first config in `resources/css/app.css`
- Dark mode via `.dark` class

**Deprecated v3 → v4 replacements:**
| v3 (Don't use)      | v4 (Use this)   |
| ------------------- | --------------- |
| `bg-opacity-50`     | `bg-black/50`   |
| `text-opacity-*`    | `text-black/50` |
| `flex-shrink-0`     | `shrink-0`      |
| `flex-grow`         | `grow`          |
| `overflow-ellipsis` | `text-ellipsis` |

# UI & Styling

- Use Radix UI and Tailwind for components and styling
- Mobile-first responsive design
- **Component Design:**
  - Components should not have external margins. Accept `className` props for positioning.
  - Do not design assuming the parent knows the child's internal structure (Separation of layout responsibilities).
- **Spacing preferences:**
  - Avoid `margin-bottom` and `margin-right`.
  - When RTL support is needed, prefer logical `ms-*`/`me-*`; otherwise use `margin-top` and `margin-left`.
  - Prefer `gap` in Flexbox/Grid containers over margins.

# Accessibility

- **Icon-only links/buttons:** When a link or button contains only an icon (no visible text), provide an accessible name using one of:
  - `aria-label` on the `<Link>` or `<button>` element (preferred for interactive elements)
  - `alt` prop on `<AppLogoIcon>` or `<img>` (for informative images)
  - `<span className="sr-only">` for visually hidden text
- **Decorative images:** When an image is purely decorative (meaning is conveyed by adjacent text), use `alt=""` to hide it from screen readers.
- **`AppLogoIcon` policy:** Defaults to `alt=""` (decorative). When used in icon-only links, add `aria-label` to the parent `<Link>` element.

# SEO & Meta Tags

Use Inertia's `<Head>` for per-page title/description. Since SSR is disabled (XServer constraint), set critical SEO meta in `app.blade.php` from PHP for reliable crawler indexing.

**Page-level (React):**

```tsx
import { Head } from '@inertiajs/react'
;<Head title="Dashboard">
  <meta name="description" content="User dashboard overview" />
</Head>
```

**Shared meta (app.blade.php):** OG image, author, etc. are set server-side.

# Performance

- Use `Promise.all` to fetch independent data in parallel.
- Minimize `useEffect`/`useState`; prefer Inertia props
- Use deferred props, `<Suspense>`, dynamic imports
- **Bundle Optimization:**
  - Avoid large barrel file imports; prefer direct imports.
  - Use dynamic loading for heavy non-critical components.

# E2E Testing

Use Playwright for E2E tests. Tests are located in `e2e/tests/`.

**Prerequisites (Local):**

1. Docker environment running (`mise run up`)
2. Vite dev server running (`pnpm dev` at port 5173)

```bash
pnpm test:e2e           # Run all E2E tests
pnpm test:e2e:ui        # Interactive UI mode
pnpm test:e2e:headed    # Run with browser visible
```

**Structure:**

```
e2e/
├── pages/        # Page Object Models
└── tests/        # Test files (*.spec.ts)
```

**Environment differences:**

| Environment | baseURL | Server |
|-------------|---------|--------|
| Local | `http://localhost:8080` | Docker (Nginx) + Vite (5173) |
| CI | `http://localhost:8000` | `php artisan serve` |

**Guidelines:**

- Test titles in Japanese for readability
- Use Page Object Model for reusable interactions

# Tooling

`pnpm dev` / `pnpm build` / `pnpm lint` / `pnpm lint:typecheck`

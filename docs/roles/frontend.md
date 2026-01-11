You are an expert in TypeScript, React 19, Inertia.js v2, and Tailwind CSS v4.

# Environment

- Use `pnpm` (not npm). Run `pnpm lint` / `pnpm format` after changes.
- See `docs/agent/boost.md` for Laravel Boost rules.

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

- Prefer `interface` over `type`
- Avoid enums; use `as const`

# Navigation & Forms

Use Inertia `<Link>` / `router.visit()` with Wayfinder:
```tsx
import { show } from '@/actions/App/Http/Controllers/UserController';
<Link {...show.link(id)}>View</Link>
```

Forms with Wayfinder:
```tsx
<Form {...store.form()}>{({ errors, processing }) => (...)}</Form>
```

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

# useEffect Policy

useEffect is **only** for external sync (API, WebSocket, browser APIs, timers).

**Anti-patterns:**
- Copying props into state
- Running logic on flag changes
- Handling user actions in effects
- Updating derived/validation states

**Principles:**
- Derive values during render
- Handle user actions in event handlers
- Add comment explaining external resource being synced

# SEO & Meta Tags

Use Inertia's `<Head>` for per-page title/description. Since SSR is disabled (XServer constraint), set critical SEO meta in `app.blade.php` from PHP for reliable crawler indexing.

**Page-level (React):**
```tsx
import { Head } from '@inertiajs/react'

<Head title="Dashboard">
  <meta name="description" content="User dashboard overview" />
</Head>
```

**Shared meta (app.blade.php):** OG image, author, etc. are set server-side.

# Performance

- Minimize `useEffect`/`useState`; prefer Inertia props
- Use deferred props, `<Suspense>`, dynamic imports

# Tooling

`pnpm dev` / `pnpm build` / `pnpm lint` / `pnpm format` / `pnpm types`

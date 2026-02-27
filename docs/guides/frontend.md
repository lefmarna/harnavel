# Frontend Guide

## Scope

Use this file for day-to-day frontend implementation in React + Inertia.
For generated framework rules, see `docs/generated/boost.md`.

This guide intentionally keeps project-specific frontend rules that are not fully specified in generated Boost guidance.

## Stack

- React 19
- Inertia v2
- TypeScript strict mode
- Tailwind CSS v4
- shadcn/ui + Radix UI

Key decisions:

| Decision | Chose | Rationale |
| --- | --- | --- |
| Architecture | SPA + Inertia.js | Classic monolithic convenience with modern SPA feel |
| State management | Inertia (Server) + React (Local) | Single source of truth from server; minimal client-side global state |
| Styling | Tailwind CSS v4 | Utility-first, design tokens in CSS variables |
| UI primitives | shadcn/ui + Radix UI | Accessible, composable, customizable |

## Project Structure

`resources/js/`:
- `pages/` Inertia pages
- `components/` shared app components
- `components/ui/` primitive UI components
- `layouts/` page shells
- `hooks/` custom hooks
- `lib/` utilities
- `actions/` Wayfinder controller action functions
- `routes/` Wayfinder named route functions
- `types/` shared type declarations

## Type Definition Ownership

`resources/js/types/` responsibilities:
- `auth.ts`: auth/session-related domain types (`User`, `Auth`, `SharedData`)
- `navigation.ts`: navigation/breadcrumb types
- `ui.ts`: UI component-facing shared types
- `index.ts`: type re-export entry
- `global.d.ts`: global/inertia type augmentation
- `vite-env.d.ts`: Vite environment typing

Rules:
- When shared Inertia props change, update both `SharedData` in `auth.ts` and matching augmentation in `global.d.ts`.
- Keep page props explicitly typed with shared/domain types from `resources/js/types/`.

## Naming and Exports

- Files and directories: kebab-case
- Components: PascalCase
- Hooks: `use` prefix
- Use named exports

## TypeScript Rules

- Prefer functional programming style.
- Avoid `any`; use `unknown` + narrowing.
- Prefer `interface` for object shapes.
- Avoid `enum`; use `as const` objects.
- Avoid type assertions (`as`); design for type inference or use type narrowing.
- Prefer `map`/`filter`/`reduce` over loops; use `forEach` only for side effects (API calls, DOM updates).
- Use `const` only; `let` is allowed only for `for` loop indices.
- Prefer `switch` over `if-else` for discrete values; always include `default` with exhaustiveness check.

### TypeScript Patterns

```ts
// Prefer transformation methods over mutable loops
const total = items.reduce((sum, x) => sum + x.price, 0)

// Prefer switch for discrete UI states
switch (status) {
  case 'pending':
    return <Pending />
  case 'done':
    return <Done />
  default: {
    const _exhaustive: never = status
    return _exhaustive
  }
}
```

## React State and Effects

- Inertia (Server State): Passed via props (`user`, `errors`, `flash`). Primary source of truth.
- React (Local State): `useState` for UI (e.g., modals), `useReducer` for complex forms.
- Global State: Avoided. Use Context only for theme/user preferences if needed.
- Business logic extracted to custom hooks.
- Prop drilling: max 3 levels (use Composition or Context beyond that).
- Use `useEffect` only for external synchronization:
  - API calls
  - WebSocket/browser API subscriptions
  - timers
- Add comment explaining external resource being synced.

Avoid using `useEffect` for:
- copying props/derived values into local state
- running logic in response to flag changes
- handling user actions in effects
- one-time initialization with empty dependency array (use lazy state initializer `useState(() => init())` or module-level initialization instead)
- updating derived or validation states within effects

## Component Placement Rules

Where does this new component go?
- Used in 1 page only: colocate in the page file or nearby
- Generic UI primitive: `components/ui/`
- Used across 2+ pages: `components/`
- Layout scaffolding: `layouts/`

Component styling rules:
- **No external margins.** Components accept `className` for positioning.
- Prefer `gap` in Flex/Grid. Avoid `margin-bottom`/`margin-right`. Use `mt-*`/`ml-*` when margins are needed.

## UI and Tailwind

- Use Tailwind v4 syntax only.
- Keep components position-agnostic (accept `className`, avoid hardcoded external margins).
- Prefer `gap-*` in flex/grid containers over margin chains.
- Use semantic elements and accessible labels/roles.
- Use shadcn/ui + Radix primitives as the default base for interactive UI.

### Tailwind v4 Migration Guard

Do not introduce deprecated v3-style utilities:

| Deprecated | Use instead |
| --- | --- |
| `bg-opacity-50` | `bg-black/50` |
| `text-opacity-*` | `text-black/50` |
| `flex-shrink-0` | `shrink-0` |
| `flex-grow` | `grow` |
| `overflow-ellipsis` | `text-ellipsis` |

## Design Tokens and Motion

- Design tokens are defined in `resources/css/app.css` (`:root` and `.dark`).
- Use CSS variables for colors (`--primary`, `--background`, `--foreground`, etc) instead of hardcoded values.
- Keep animations subtle and purposeful. No decorative motion.
  - Fast: `100ms` (Hover, Click)
  - Normal: `200ms` (Page Transition, Modal Open)
  - Slow: `300ms` (Complex Expansion, Drawer)
- Respect `prefers-reduced-motion`.

## Performance Baseline

- Page-level code splitting is automatic (Inertia `resolvePageComponent` + `import.meta.glob`).
- For heavy in-page components, use `React.lazy` + loading fallback.
- Use `useMemo` / `useCallback` only when expensive re-computation or prop identity churn is measurable.
- `loading="lazy"` on images below the fold.
- Specify `width` and `height` to prevent layout shift.

## Screen Map and Access Guards

Use this map as the default navigation and guard contract.

| Area | Route | Layout | Guard |
| --- | --- | --- | --- |
| Public | `/` | standalone | public |
| Auth (guest) | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/two-factor-challenge` | auth pages | `guest` |
| Auth (signed-in) | `/email/verify` | auth pages | `auth` |
| Auth (signed-in) | `/user/confirm-password` | auth pages | `auth` |
| Protected | `/dashboard` | app/sidebar | `auth` + `verified` |
| Protected settings entry | `/settings` | redirect to `/settings/profile` | `auth` |
| Protected settings | `/settings/profile` | app + settings | `auth` |
| Protected settings | `/settings/password`, `/settings/appearance`, `/settings/two-factor` | app + settings | `auth` + `verified` |

Layout definitions:
- **AuthLayout**: `/login`, `/register`, `/forgot-password`, etc. Centered card, no sidebar.
- **AppLayout**: Dashboard, Settings. Includes Sidebar + Header + UserMenu.
- **Settings Layout**: Nested inside AppLayout with its own sub-navigation (Profile, Password, Two-Factor, Appearance).

Middleware:
- `auth` + `verified`: Protects Dashboard/Settings.
- `guest`: Protects Login/Register (redirects to Dashboard if logged in).

Rules:
- Keep route-guard decisions in `routes/web.php` and `routes/settings.php`.
- Keep auth entry routes aligned with Fortify registered routes.
- New page work must define expected layout and guard in the same change.

## SEO

- Default metadata is server-rendered in Blade.
- Page-level metadata is updated via Inertia `<Head>`.
- Keep title/description unique per page.

SEO minimums for user-facing pages:
- unique title and description
- canonical URL when applicable
- Open Graph title/description/image for share-critical pages

### Required Meta and Social Tags

| Tag | Baseline |
| --- | --- |
| `title` | unique, ideally under 60 chars |
| `description` | unique, ideally under 160 chars |
| `canonical` | absolute self URL where applicable |
| `robots` | `index, follow` unless intentionally private |
| `og:type`, `og:title`, `og:description`, `og:image`, `og:url` | required for shareable pages |
| `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` | required for X/Twitter previews |

### SEO Implementation Baseline

- Shared default meta tags live in `resources/views/app.blade.php`.
- Page-level overrides are done with Inertia `<Head>`.
- OGP image file baseline: `public/og-image.png` (recommended `1200x630`).

Required outputs for template-based production projects:
- `robots.txt` lives at `public/robots.txt` (or an explicitly documented route equivalent).
- `sitemap.xml` lives at `public/sitemap.xml` (or an explicitly documented route equivalent).
- `sitemap` update trigger is defined in release flow (CI/deploy step or scheduled job).
- JSON-LD placement is defined for rich-result pages (page-level `<Head>` or a shared SEO wrapper).

Optional extensions:
- shared SEO component abstraction (for example, page-level wrapper around `<Head>`)
- external package adoption for sitemap generation

### SEO Verification

- Validate page title/description changes on client-side navigation.
- Check rendered HTML source for baseline server-side meta tags.
- Validate OGP/Twitter preview for key public pages.
- Run Lighthouse SEO checks for representative pages.
- Verify robots and sitemap endpoints/files are accessible and consistent with indexing policy.
- Validate structured data output for pages where JSON-LD is required.

## E2E Testing (Playwright)

- New pages should include at least a smoke test.
- Semantic locators (`getByRole`, `getByLabel`) over CSS selectors.
- Test titles in Japanese.
- Do not use `sleep()`; wait with Playwright states/assertions.
- After navigation, use explicit ready-state waiting (for example `waitForLoadState('networkidle')`) where rendering depends on async data.

Selector priority:
1. `getByRole('heading', { name: '...' })` - semantic (preferred)
2. `getByLabel('...')` - form elements
3. `locator('[data-testid="..."]')` - custom data attributes (fallback)
4. `locator('a[href="..."]')` - link by href (last resort)

CI assumptions:
- Chromium-only by default in CI
- Full browser matrix can run on demand
- CI-friendly Playwright defaults: retries on CI, single worker on CI, trace/screenshot/video on failure

## Verification Checklist

- [ ] Types are explicit and no `any` was introduced
- [ ] Discrete state branching uses `switch` with exhaustiveness checks where practical
- [ ] Tailwind v4 classes are used
- [ ] CSS variable design tokens are used for theme colors
- [ ] Semantic/accessible markup is preserved
- [ ] `useEffect` is used only for external synchronization
- [ ] Heavy UI paths consider lazy loading/memoization where needed
- [ ] SEO tags are updated when page meaning changes
- [ ] Route guard and layout decisions are documented for newly added pages
- [ ] Shared props changes update `SharedData` and related global type augmentation
- [ ] E2E coverage includes new user-visible behavior
- [ ] Responsive behavior is verified when layout/viewport behavior changes

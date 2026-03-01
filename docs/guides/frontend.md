# Frontend Guide

## Scope

Use this file only for frontend rules that are project-specific and not obvious from React, Inertia, Tailwind, or `docs/generated/boost.md`.

## Type Definition Ownership

- `auth.ts` owns auth/session types, including shared Inertia props.
- `navigation.ts` owns breadcrumb and navigation types.
- `ui.ts` owns cross-component UI types.
- `global.d.ts` owns Inertia type augmentation.
- When shared Inertia props change, update both `resources/js/types/auth.ts` and `resources/js/types/global.d.ts`.

## TypeScript Rules

- Avoid `any`; use `unknown` + narrowing.
- Avoid type assertions (`as`); design for type inference or use type narrowing.
- Use `const`; use `let` only for loop indices.

## State and Effects

- Treat Inertia props (`user`, `errors`, `flash`, page props) as the primary source of server state.
- Global state is avoided. Use Context only for theme or user preferences when necessary.
- Use `useEffect` only for external synchronization:
  - API calls
  - WebSocket or browser API subscriptions
  - timers
- Do not use `useEffect` for:
  - copying props or derived values into local state
  - running logic in response to flag changes
  - handling user actions in effects
  - one-time initialization with an empty dependency array; use a lazy state initializer (`useState(() => init())`) or module-level initialization instead
  - updating derived or validation state within effects

## Component Rules

- Components should not rely on external margins. Use `className` for placement.

## Layout Spacing Rules

- Prefer `gap` for layout spacing.
- When adding margin during page/layout composition, prefer `mt-*` / `ml-*` over `mb-*` / `mr-*`.

## Tailwind v4 Migration Guard

Do not introduce deprecated v3-style utilities:

| Deprecated | Use instead |
| --- | --- |
| `bg-opacity-50` | `bg-black/50` |
| `text-opacity-*` | `text-black/50` |
| `flex-shrink-0` | `shrink-0` |
| `flex-grow` | `grow` |
| `overflow-ellipsis` | `text-ellipsis` |

## Screen Map and Access Guards

This is the default navigation contract for the template. Update this section when a cloned project changes page structure.

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

- `AuthLayout`: guest/auth entry pages
- `AppLayout`: dashboard and authenticated pages
- `Settings Layout`: nested settings pages
- Keep route + guard decisions in `routes/web.php` and `routes/settings.php`.
- Keep auth entry routes aligned with Fortify routes.
- When adding pages, define layout and guard in the same change.

## E2E Conventions

- New pages must include at least a smoke test.
- Prefer semantic locators (`getByRole`, `getByLabel`).
- Test titles in Japanese.

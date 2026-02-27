# Frontend Guide

## Scope

Use this file for day-to-day frontend implementation in React + Inertia.
For generated framework rules, see `docs/generated/boost.md`.

This guide intentionally keeps project-specific frontend rules that are not fully specified in generated Boost guidance.

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

## TypeScript Rules

- Avoid `any`; use `unknown` + narrowing.
- Avoid type assertions (`as`); design for type inference or use type narrowing.
- Use `const` only; `let` is allowed only for `for` loop indices.

## React State and Effects

- Inertia (Server State): Passed via props (`user`, `errors`, `flash`). Primary source of truth.
- Global State: Avoided. Use Context only for theme/user preferences if needed.
- Use `useEffect` only for external synchronization:
  - API calls
  - WebSocket/browser API subscriptions
  - timers

Avoid using `useEffect` for:
- copying props/derived values into local state
- running logic in response to flag changes
- handling user actions in effects
- one-time initialization with empty dependency array (use lazy state initializer `useState(() => init())` or module-level initialization instead)
- updating derived or validation states within effects

## Component Placement Rules

Component styling rules:
- **No external margins.** Components accept `className` for positioning.
- Prefer `gap` in Flex/Grid. Avoid `margin-bottom`/`margin-right`. Use `mt-*`/`ml-*` when margins are needed.

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

## E2E Testing Baseline

- New pages must include at least a smoke test.
- Semantic locators (`getByRole`, `getByLabel`) over CSS selectors.
- Test titles in Japanese.
- Do not use `sleep()`; wait with Playwright states/assertions.

# Frontend Design

## Contents

- [Key Decisions](#key-decisions)
- [Directory Structure](#directory-structure)
- [State Management](#state-management)

## Key Decisions

These are the project-specific choices that differ from defaults:

| Decision         | Chose                            | Rationale                                                            |
| ---------------- | -------------------------------- | -------------------------------------------------------------------- |
| Architecture     | SPA + Inertia.js                 | Classic monolithic convenience with modern SPA feel                  |
| State management | Inertia (Server) + React (Local) | Single source of truth from server; minimal client-side global state |
| Styling          | Tailwind CSS v4                  | Utility-first, design tokens in CSS variables                        |
| UI primitives    | Shadcn/ui + Radix UI             | Accessible, composable, customizable                                 |

## Directory Structure

```
resources/js/
├── app.tsx               # Entry point
├── components/           # Shared UI (see components.md)
│   └── ui/               # Shadcn/Radix primitives
├── layouts/              # Page shells (Sidebar, Navbar)
├── pages/                # Inertia page components
├── hooks/                # Custom React hooks
├── lib/                  # Utilities
└── types/                # Type definitions
```

**Naming**: kebab-case files/dirs, PascalCase components, `use` prefix for hooks, named exports.

## State Management

- **Inertia (Server State)**: Passed via props (`user`, `errors`, `flash`). Primary source of truth.
- **React (Local State)**: `useState` for UI (e.g., modals), `useReducer` for complex forms.
- **Global State**: Avoided. Use Context only for theme/user preferences if needed.

## Verification Checklist

- [ ] **Component Logic**: Business logic extracted to custom hooks
- [ ] **Prop Drilling**: Max 3 levels (use Composition or Context otherwise)
- [ ] **Reusability**: UI components are generic and domain-agnostic
- [ ] **Performance**: Heavy components lazy-loaded (`React.lazy`)
- [ ] **Accessibility**: Semantic HTML (`<button>`, `<nav>`, `<main>`) used
- [ ] **State**: Server state (Inertia) used over client-side fetching where possible

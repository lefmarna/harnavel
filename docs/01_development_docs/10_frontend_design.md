# Frontend Design

This document defines the frontend component design and architecture.
It ensures code organization and state management consistency.

## 1. Architecture (SPA + Inertia.js)

- **Entry Point**: `resources/js/app.tsx`.
- **Components**: Functional components only (No class components).
- **Hooks**: Use custom hooks for shared logic (`useForm`, `useUser`).
- **Layouts**: Use Inertia Layouts for shared UI (`AppLayout.tsx`).

## 2. Component Structure

- **Atomic Design** (Simplified):
  - **ui/**: Basic UI elements (Button, Input, Card). No domain logic.
  - **features/**: Complex components with business logic (LoginForm, UserTable).
  - **layouts/**: Page shells (Sidebar, Navbar).
  - **pages/**: Inertia page components (connected to routes).

## 3. State Management

- **Inertia (Server State)**: Primary source of truth.
  - Passed via props (`user`, `errors`, `flash`).
- **React (Local State)**: UI-specific state.
  - Use `useState` for simple UI state (modal open/close).
  - Use `useReducer` for complex form logic.
- **Global State (Avoid)**:
  - Avoid Redux/Zustand unless absolutely necessary for cross-component state.
  - Use React Context for theme/user preferences.

## 4. Styling (Tailwind CSS)

- **Utility-First**: Use Tailwind classes directly.
- **Consistency**: Use design tokens (colors, spacing) defined in `tailwind.config.js`.
- **Optimization**: Use `@apply` sparingly.

## Verification Checklist

- [ ] **Component Logic**: Is business logic separated from UI (hooks)?
- [ ] **Prop Drilling**: Is prop drilling minimal (< 3 levels)? (Use Context or Composition).
- [ ] **Reusability**: Are UI components generic and reusable?
- [ ] **Performance**: Are heavy components lazy-loaded (`React.lazy`)?
- [ ] **Accessibility**: Are semantic HTML tags used (`<button>`, `<nav>`, `<main>`)?
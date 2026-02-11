# Component Design

This document defines the component library architecture and guidelines.
It ensures UI consistency and code reusability.

## 1. Categories (Atomic Design)

### Atoms (Primitives)

- **Button**: `Button` (Primary, Secondary, Outline, Ghost, Link).
- **Input**: `Input` (Text, Email, Password).
- **Label**: `Label`.
- **Badge**: `Badge` (Status indicators).
- **Icon**: `LucideIcon`.

### Molecules (Form Elements)

- **Form Field**: `FormItem` (Label + Input + Error).
- **Card**: `Card` (Header, Content, Footer).
- **Modal**: `Dialog` (Trigger, Content, Close).
- **Alert**: `Alert` (Info, Warning, Error).

### Organisms (Sections)

- **Navbar**: Logo, Navigation Links, User Menu.
- **Sidebar**: Dashboard Navigation.
- **Footer**: Copyright, Links.
- **Table**: `DataTable` (Pagination, Sorting, Filtering).

### Templates (Layouts)

- **Auth Layout**: Center-aligned card.
- **Dashboard Layout**: Sidebar + Header + Content.
- **Landing Layout**: Hero + Features + Footer.

## 2. Implementation Guidelines (Shadcn UI)

- **Radix UI**: Use for logic and accessibility (`@radix-ui/*`).
- **Tailwind CSS**: Use for styling via `cn()` helper (clsx/tailwind-merge).
- **Slots**: Use `Slot` for polymorphism (`asChild` prop).
- **Variants**: Use `cva` (class-variance-authority) for variant definitions.

## 3. Custom Components

### Directory Structure

- `resources/js/components/ui/`: Base components (Shadcn).
- `resources/js/components/`: Reusable custom components.
- `resources/js/pages/`: Inertia Page components.

## Verification Checklist

- [ ] **Accessibility**: Use semantic HTML (`<button>`, `<label>`, `<nav>`).
- [ ] **Keyboard**: Can interact with Tab/Enter/Esc keys?
- [ ] **Focus**: Visible focus ring on interactive elements (`ring-offset-background`).
- [ ] **Props**: Are props typed with TypeScript interfaces?
- [ ] **Composition**: Are components composable (children/slots)?
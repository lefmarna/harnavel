# Component Conventions

## Taxonomy

Components in `resources/js/components/` are organized by **purpose**:

| Directory  | Contains                    | Examples                              |
| ---------- | --------------------------- | ------------------------------------- |
| `ui/`      | shadcn/Radix primitives     | Button, Input, Dialog, Card           |
| `common/`  | Shared app-level components | FlashMessage, SearchInput, Pagination |
| `layout/`  | Page structure              | Header, Footer, Sidebar               |

## Placement Decision

**Where does this new component go?**

- Used in **1 page only** → colocate in the page file or nearby
- **Generic UI** primitive → `ui/`
- Used across **2+ pages** → `common/`
- **Layout** scaffolding → `layout/`

## Design Rules

**No external margins.** Components accept `className` for positioning:

```tsx
// Good
;<MyComponent className="mt-4" />

// Bad - component owns its margin
function MyComponent() {
  return <div className="mt-4">...</div>
}
```

**Spacing**: Prefer `gap` in Flex/Grid. Avoid `margin-bottom`/`margin-right`. Use `mt-*`/`ml-*` when margins are needed.

**Styling**: Tailwind v4 (not v3 syntax), mobile-first, shadcn/ui + Radix as base. See [coding guide](coding-guide.md) for Tailwind v4 class migration table.

## Verification Checklist

- [ ] **Placement**: Component in correct taxonomy directory (see decision above)
- [ ] **Margins**: No external margins (accepts `className` prop for positioning)
- [ ] **Spacing**: `gap` used in Flex/Grid containers (not `margin-bottom`/`margin-right`)
- [ ] **Tailwind**: v4 class names used (not deprecated v3 utilities)
- [ ] **Semantics**: HTML elements used (`<button>`, `<nav>`, `<main>`, etc.)

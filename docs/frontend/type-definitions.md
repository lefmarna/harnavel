# Type Definitions

## Directory Structure

```
resources/js/types/
├── index.d.ts          # Domain types (User, Auth, SharedData, Nav)
└── vite-env.d.ts       # Vite environment declarations
```

## Conventions

- **Strict mode**: `"strict": true` in tsconfig.json
- **No `any`**: Use `unknown` and narrow with type guards
- **Domain types**: `resources/js/types/index.d.ts`
- **Shared data**: `SharedData` interface for Inertia shared props (User, Flash)

## Key Domain Types

**User & Auth** (`index.d.ts`):

- `User` - User model (id, name, email, two_factor_enabled)
- `Auth` - Authentication wrapper (`{ user: User }`)
- `SharedData` - Inertia shared props (auth, sidebar state, quote)

**Navigation**:

- `NavGroup` / `NavItem` - Sidebar navigation structure
- `BreadcrumbItem` - Breadcrumb trail

## Verification Checklist

- [ ] New domain types added to `types/index.d.ts`
- [ ] No `any` usage (use `unknown` with type guards)
- [ ] Inertia page props typed with explicit interfaces
- [ ] `SharedData` updated when new shared props are added

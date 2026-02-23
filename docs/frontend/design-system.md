# Design System

Shadcn/ui + Radix + Tailwind v4. All design tokens in `resources/css/app.css`.

## Color System

CSS variables with oklch values. Light/dark mode switching via `.dark` class.

| Token           | Usage                        |
| --------------- | ---------------------------- |
| `--primary`     | Buttons, links, active state |
| `--background`  | Page background              |
| `--foreground`  | Body text                    |
| `--destructive` | Error, delete                |
| `--muted`       | Subdued backgrounds          |
| `--accent`      | Highlights, hover            |
| `--border`      | Borders, dividers            |
| `--input`       | Form input borders           |
| `--ring`        | Focus rings                  |

Chart palette: `--chart-1` through `--chart-5` for data visualization.

Sidebar tokens: `--sidebar`, `--sidebar-primary`, `--sidebar-accent`, etc.

Concrete values are defined in `resources/css/app.css` (`:root` for light, `.dark` for dark).

## Typography

Default font defined via `--font-sans` in `@theme`. Update this when changing the project's typeface.

## Spacing & Radius

Base radius defined via `--radius` in `:root`. Derived tokens:

| Token         | Derivation              |
| ------------- | ----------------------- |
| `--radius-lg` | `var(--radius)`         |
| `--radius-md` | `var(--radius) - 2px`   |
| `--radius-sm` | `var(--radius) - 4px`   |

Spacing uses Tailwind's default scale (4px base). Prefer `gap` over margins.

## Animation

Keep animations subtle and purposeful. No decorative motion.

Timing:

- **Fast**: 100ms (Hover, Click).
- **Normal**: 200ms (Page Transition, Modal Open).
- **Slow**: 300ms (Complex Expansion, Drawer).

## Layout

- **Auth pages**: Standalone centered layout, no sidebar
- **Mobile-first**: Write base styles for mobile, add `lg:` for desktop
- **Container**: `max-w-7xl mx-auto px-4`
- **Responsive breakpoints**: Tailwind defaults (sm: 640px, md: 768px, lg: 1024px, xl: 1280px)

## Verification Checklist

- [ ] Colors use CSS variables (not hardcoded hex/oklch values)
- [ ] Dark mode supported (`.dark` class switching)
- [ ] Animations use standard timing (100-300ms for UI interactions)
- [ ] Animation respects `prefers-reduced-motion`
- [ ] Mobile-first responsive (`lg:` breakpoint for desktop overrides)
- [ ] Layout stacks correctly on mobile

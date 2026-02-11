# Layout System

This document defines the layout system and responsive design.
It ensures visual consistency and responsiveness.

## 1. Grid System (12 Columns)

- **Columns**: 12-column grid (`grid-cols-12`).
- **Gutter**: Use `gap-4` (16px) or `gap-8` (32px).
- **Container**: Use `max-w-7xl` or custom container (`mx-auto px-4`).

## 2. Responsive Breakpoints (Tailwind Defaults)

- **xs**: `< 640px` (Mobile Portrait).
- **sm**: `>= 640px` (Mobile Landscape).
- **md**: `>= 768px` (Tablet).
- **lg**: `>= 1024px` (Small Desktop).
- **xl**: `>= 1280px` (Large Desktop).
- **2xl**: `>= 1536px` (Wide Desktop).

## 3. Implementation Guidelines

- **Mobile First**: Develop for smaller screens first, then progressively enhance (`md:`, `lg:`).
- **Fluid Typography**: Use `rem` units for font sizes (`text-base`, `text-lg`).
- **Flexbox**: Use `flex`, `flex-row`, `justify-between`, `items-center` for layout components.
- **Grid**: Use `grid`, `grid-cols-*`, `gap-*` for data grids and cards.

## 4. Layout Patterns

- **Centered**: `flex items-center justify-center min-h-screen`.
- **Sidebar**: `grid grid-cols-[240px_1fr]` (Desktop).
- **Dashboard**: `grid grid-rows-[auto_1fr]`.
- **Masonry**: Use `columns-*` for masonry layouts.

## Verification Checklist

- [ ] **Mobile**: Does the layout stack correctly on mobile?
- [ ] **Tablet**: Does the layout adapt to medium screens (768px)?
- [ ] **Desktop**: Does the layout expand correctly on large screens (1280px)?
- [ ] **Overflow**: Is horizontal scrollbar avoided (`overflow-x-hidden`)?
- [ ] **Spacing**: Is whitespace consistent (`gap-4`, `p-4`, `m-4`)?
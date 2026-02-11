# Design Principles

This document defines the core design principles, color system, and typography.
It ensures brand consistency and readability.

## 1. Principles

- **Clarity**: Use whitespace, hierarchy, and typography to guide user focus.
- **Feedback**: Provide immediate visual feedback for user actions.
- **Consistency**: Use standardized components and patterns.

## 2. Color System (Tailwind CSS)

### Brand Colors

- **Primary**: Brand color (`indigo-600` / `#4F46E5`).
- **Secondary**: Action color (`gray-800` / `#1F2937`).

### Semantic Colors

- **Success**: (`green-600` / `#16A34A`).
- **Warning**: (`amber-500` / `#F59E0B`).
- **Error**: (`red-600` / `#DC2626`).
- **Info**: (`blue-500` / `#3B82F6`).

### Neutrals

- **Text**: `slate-900` (High contrast), `slate-500` (Subtext).
- **Background**: `white`, `slate-50` (Section bg), `slate-100` (Card bg).
- **Border**: `slate-200` (Divider), `slate-300` (Input).

## 3. Typography

### Font Family

- **Sans-Serif**: `Inter`, `Figtree` (Google Fonts).
- **Serif**: (Optional for headings) `Merriweather`.
- **Mono**: `Fira Code`, `JetBrains Mono`.

### Scale (Tailwind)

- **H1**: `text-4xl font-bold` (36px).
- **H2**: `text-3xl font-semibold` (30px).
- **H3**: `text-2xl font-semibold` (24px).
- **Body**: `text-base` (16px).
- **Small**: `text-sm` (14px).
- **Tiny**: `text-xs` (12px).

## 4. Spacing (4px Base)

- **xs**: `gap-1` (4px).
- **sm**: `gap-2` (8px).
- **md**: `gap-4` (16px).
- **lg**: `gap-6` (24px).
- **xl**: `gap-8` (32px).
- **2xl**: `gap-12` (48px).

## Verification Checklist

- [ ] **Contrast**: Check WCAG AA compliance (4.5:1 ratio).
- [ ] **Scale**: Are headings consistent across pages?
- [ ] **Spacing**: Is whitespace consistent (multiples of 4px)?
- [ ] **Line Height**: Is body text readable (`leading-relaxed`)?
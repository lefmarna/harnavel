# Basic Design System

This document defines the design system overview and quick start guide.
It ensures visual consistency and user experience.

## 1. Philosophy

- **Consistency**: Maintain visual harmony across the application.
- **Utility-First**: Use Tailwind CSS for rapid prototyping and maintainable styles.
- **Accessibility (a11y)**: Use Radix UI primitives for keyboard navigation and ARIA support.
- **Components**: Reusable, modular components (Shadcn UI).

## 2. Quick Start

### Installation

```bash
# Install Shadcn UI
npx shadcn-ui@latest init
# Install Radix UI primitives
npm install @radix-ui/react-dialog @radix-ui/react-slot ...
```

### Usage

1.  **Import**: `import { Button } from "@/components/ui/button"`
2.  **Variants**: `<Button variant="outline">Action</Button>`
3.  **Override**: `<Button className="bg-red-500">Destructive</Button>`

## 3. Tooling

- **Tailwind CSS**: Utility classes.
- **Radix UI**: Headless accessible components.
- **Lucide React**: Icons.
- **Figma**: (Optional) Design files sync.

## Verification Checklist

- [ ] **Consistency**: Are standard components used instead of custom styles?
- [ ] **Accessibility**: Do interactive elements have focus states?
- [ ] **Responsiveness**: Does the layout break on mobile (< 640px)?
- [ ] **Dark Mode**: Is text readable in dark mode (`dark:` classes)?
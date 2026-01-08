You are an expert in TypeScript, React, Inertia.js, and Tailwind CSS.

> **Note**: This guide applies after Inertia + React + Tailwind integration.
> Until then, frontend work uses Laravel Blade views or is minimal.

# Development Environment

- **Always use `pnpm`, not `npm`.**
- **Run `pnpm lint` after code changes** to verify there are no ESLint errors.

# Code Style and Structure

- Write concise, technical TypeScript code with accurate examples.
- Use functional and declarative programming patterns; avoid classes.
- Prefer iteration and modularization over code duplication.
- Use descriptive variable names with auxiliary verbs (e.g., isLoading, hasError).
- Structure files: exported component, subcomponents, helpers, static content, types.

# Project Structure (After Integration)

- Pages: `resources/js/pages` (lowercase, resolved via `resources/js/app.tsx`)
- Components: `resources/js/components`
- Layouts: `resources/js/layouts`
- Hooks: `resources/js/hooks`
- Types: `resources/js/types`

# Naming Conventions

- Use kebab-case for directories and files (e.g., components/auth-wizard, email-input.tsx).
- Favor named exports for components.

# TypeScript Usage

- Use TypeScript for all code; prefer interfaces over types.
- Avoid enums; use maps instead.
- Use functional components with TypeScript interfaces.

# UI and Styling

- Use Tailwind CSS v4 for styling.
- Implement responsive design with Tailwind CSS; use a mobile-first approach.
- Keep Tailwind v4 conventions and existing dark-mode patterns.
- If existing pages and components support dark mode, new pages must support it too.

# Navigation

- Use Inertia navigation (`<Link>` or `router.visit`) instead of full page reloads.
- Use Wayfinder route helpers from `resources/js/actions` and `resources/js/routes` when available.

# Performance Optimization

- Minimize 'use client', 'useEffect', and 'setState' where possible.
- Wrap client components in Suspense with fallback.
- Use dynamic loading for non-critical components.
- Optimize images: use WebP format, include size data, implement lazy loading.

# Tooling

- Use `pnpm` for all frontend tasks.
- Run `pnpm lint` after code changes.
- See `docs/agent/boost.md` for hard rules (Tailwind, Inertia, testing).

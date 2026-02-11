# Inertia.js Patterns

This document defines implementation patterns and best practices for Inertia.js (React).
It ensures efficient data loading and navigation.

## 1. Core Principles

- **Single Source of Truth**: Laravel controllers provide all initial page data (props).
- **Client-Side Navigation**: Use `<Link>` component for internal routes (SPA feel).
- **Manual Visits**: Use `router.visit()` for programmatic navigation.

## 2. Data Fetching Patterns

### A. Initial Page Load (Props)

- **Controller**: `Inertia::render('Users/Index', ['users' => User::all()])`
- **Component**: `export default function Index({ users }: Props) { ... }`

### B. Lazy Loading (Partial Reloads)

- **Controller**: `Inertia::lazy(fn() => User::all())`
- **Component**: `<Link href="/users" only={['users']}>Load Users</Link>`
- **Use Case**: Heavy data (lists, charts) not needed immediately.

### C. Form Submission

- **Hook**: `const { data, setData, post, processing, errors } = useForm({ ... })`
- **Submit**: `post(route('users.store'))`
- **Validation**: Display `errors.name` below input.

## 3. Best Practices

- **Avoid API Calls**: Use Inertia props instead of `useEffect` + `axios` fetch.
- **Shared Data**: Use `HandleInertiaRequests` middleware for global data (User, Flash).
- **Preserve State**: Use `preserveScroll`, `preserveState` options when needed.

## Verification Checklist

- [ ] **Navigation**: Are `<Link>` components used for internal links?
- [ ] **Forms**: Is `useForm` used for all forms (automatic CSRF/validation)?
- [ ] **Errors**: Are validation errors displayed correctly (`InputError`)?
- [ ] **Loading**: Is loading state shown during processing (`processing`)?
- [ ] **Layout**: Is the correct layout applied (`AppLayout`)?
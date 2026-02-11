# Performance Optimization

This document defines the performance strategy and techniques.
It ensures the application is fast, responsive, and efficient.

## 1. Performance Goals (Core Web Vitals)

- **LCP (Largest Contentful Paint)**: < 2.5s (Critical)
- **FID (First Input Delay)**: < 100ms (Critical)
- **CLS (Cumulative Layout Shift)**: < 0.1 (Critical)
- **FCP (First Contentful Paint)**: < 1.8s
- **TTFB (Time To First Byte)**: < 600ms

## 2. Backend Optimization (Laravel)

### Database (MySQL/MariaDB)

- **Indexing**: Add indexes to frequently queried columns (WHERE, ORDER BY).
- **Eager Loading**: Use `with()` to prevent N+1 queries (`$user->posts()->with('comments')->get()`).
- **Caching**: Use `Cache::remember()` for expensive queries (Redis/Memcached).
- **Query Caching**: Use MySQL Query Cache if available.

### Queues (Async Processing)

- **Offload**: Emails, notifications, heavy calculations to background workers (Redis/SQS).
- **Batching**: Process large datasets in chunks (`chunk()`, `cursor()`).

### PHP (Production)

- **Opcache**: Enabled (`opcache.enable=1`).
- **JIT**: Enabled (PHP 8.0+).

## 3. Frontend Optimization (React/Inertia)

### Assets (Vite)

- **Minify**: JS/CSS minification enabled (`build.minify`).
- **Code Splitting**: Dynamic imports for routes (`React.lazy`).
- **Tree Shaking**: Remove unused code.

### Images

- **Format**: Use WebP/AVIF (`<picture>`).
- **Lazy Loading**: `loading="lazy"` on images below fold.
- **Dimensions**: Specify `width` and `height` to prevent layout shift.

### Rendering

- **SSR**: (If enabled) Cache rendering where possible.
- **Memoization**: `React.memo`, `useMemo` for expensive computations.

## Verification Checklist

- [ ] **Lighthouse**: Performance score > 90.
- [ ] **N+1 Queries**: Use Laravel Debugbar/Telescope to detect N+1 issues.
- [ ] **TTFB**: Measure server response time (< 200ms ideal).
- [ ] **Bundle Size**: Analyze bundle size (`npm run analyze`).
- [ ] **Database**: Explain queries (`EXPLAIN SELECT ...`) for slow endpoints.
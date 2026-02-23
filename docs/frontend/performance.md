# Performance

## Asset Optimization (Vite)

- **Minification**: JS/CSS minification enabled in production build
- **Code Splitting**: Page-level splitting is automatic (Inertia `resolvePageComponent` + `import.meta.glob`). Use `React.lazy` for heavy in-page components
- **Tree Shaking**: Unused code removed automatically by Vite

## Image Optimization

When adding images to the project:

- **Format**: Use WebP/AVIF where possible (`<picture>`)
- **Lazy Loading**: `loading="lazy"` on images below the fold
- **Dimensions**: Specify `width` and `height` to prevent layout shift

## Memoization

Use `useMemo` / `useCallback` for expensive computations:

```typescript
const filteredItems = useMemo(
  () => items.filter(item => item.active),
  [items]
)
```

## Verification Checklist

- [ ] Heavy in-page components use `React.lazy` with loading fallback
- [ ] `useMemo` / `useCallback` for filtered/sorted list computations
- [ ] Images specify width/height (no layout shift)
- [ ] Production bundle analyzed (`pnpm build` output)

# SEO Requirements

This document defines the SEO strategy, including meta tags, sitemaps, and structured data.
It ensures the application is discoverable and optimized for search engines.

For implementation details and code examples, please refer to the **`seo-optimization` skill**.

## 1. Core Strategy (Hybrid Approach)

To support both modern browsers and legacy crawlers (Twitter/LINE bots), we use a hybrid approach:

- **Server-Side (Blade)**: Set default meta tags in `app.blade.php` using `withViewData` from controllers. This ensures OGP tags are visible to bots that don't execute JavaScript.
- **Client-Side (Inertia)**: Use `<Head>` component (via `SeoHead`) to update tags dynamically on client-side navigation.

## 2. Required Meta Tags

All pages MUST include:

- [ ] **Title**: Unique, descriptive, < 60 chars. Format: `Page Name - App Name`.
- [ ] **Description**: Concise summary, < 160 chars.
- [ ] **Canonical URL**: Self-referencing URL to prevent duplicate content issues.
- [ ] **Robots**: `index, follow` (unless restricted).

## 3. Open Graph (OG) & Twitter Card

For social sharing correctness (tested via Facebook Debugger):

- [ ] **og:type**: `website` or `article`.
- [ ] **og:url**: Full canonical URL.
- [ ] **og:title**: Same as page title.
- [ ] **og:description**: Same as meta description.
- [ ] **og:image**: High-quality image (1200x630px recommended). Absolute URL.
- [ ] **twitter:card**: `summary_large_image`.

## 4. Technical Implementation Requirements

- **Component**: Use the shared `SeoHead` component (see `seo-optimization` skill).
- **Sitemap**: Generated daily via `spatie/laravel-sitemap`.
- **Robots.txt**: Serve dynamically via `routes/web.php` to handle environment differences.
- **Structured Data**: JSON-LD embedded in `<Head>` for rich snippets.

## 5. Performance (Core Web Vitals)

- **LCP (Largest Contentful Paint)**: < 2.5s
- **FID (First Input Delay)**: < 100ms
- **CLS (Cumulative Layout Shift)**: < 0.1

## Verification Checklist

- [ ] **Lighthouse**: Run `npm run lighthouse` (or Chrome DevTools) -> SEO score > 90.
- [ ] **SSR Fallback**: View Page Source (`Cmd+Opt+U`) -> Are title/desc/OGP tags present in raw HTML? (Critical for Bots).
- [ ] **Client Update**: Click internal links -> Does the browser tab title update?
- [ ] **OGP**: Use Facebook Sharing Debugger / Twitter Card Validator.
- [ ] **Sitemap**: Is `sitemap.xml` accessible and valid?

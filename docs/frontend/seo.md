# SEO Requirements

## Core Strategy

**Hybrid Approach** (Laravel Blade + Inertia):
- **Server-Side (Blade)**: Default meta tags set in `app.blade.php`. Ensures visibility to bots without JS.
- **Client-Side (Inertia)**: `<Head>` component updates tags on navigation.

## Required Meta Tags

| Tag         | Requirement          | Format                 |
| ----------- | -------------------- | ---------------------- |
| Title       | Unique, < 60 chars   | `Page Name - App Name` |
| Description | Concise, < 160 chars | Summary of content     |
| Canonical   | Self-referencing     | Absolute URL           |
| Robots      | `index, follow`      | Unless restricted      |

## Open Graph & Twitter Card

| Property         | Value                 | Note         |
| ---------------- | --------------------- | ------------ |
| `og:type`        | `website` / `article` |              |
| `og:url`         | Canonical URL         |              |
| `og:title`       | Page Title            |              |
| `og:description` | Page Description      |              |
| `og:image`       | 1200x630px            | Absolute URL |
| `twitter:card`   | `summary_large_image` |              |

## Technical Implementation

- **Component**: Shared `SeoHead` component (see `seo-optimization` skill).
- **Sitemap**: `spatie/laravel-sitemap` (Daily generation).
- **Robots.txt**: Served via `routes/web.php` (Dynamic per env).
- **Structured Data**: JSON-LD in `<Head>`.


## Verification Checklist

- [ ] **Lighthouse**: SEO score > 90
- [ ] **Blade output**: View Source shows meta tags (for bots)
- [ ] **Client Update**: Tab title updates on navigation
- [ ] **OGP**: Validated with Facebook Debugger / Twitter Validator
- [ ] **Sitemap**: XML valid and accessible


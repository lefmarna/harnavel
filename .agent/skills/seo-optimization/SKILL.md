---
name: seo-optimization
description: Laravel + Inertia.js (React/Vue) プロジェクトにおけるSEO実装（タイトル、メタディスクリプション、OGP、構造化データ、サイトマップなど）のベストプラクティスと実装ガイド。SEO要件の実装や確認が必要な場合に使用する。
license: MIT
---

This skill provides best practices for SEO implementation in applications using the Laravel + Inertia.js (React/Vue) stack.
It systematizes methods for appropriately conveying information to search engines and social media platforms in SPA/SSR applications using Inertia.js.

## 1. Core Principles

SEO for Inertia.js applications requires managing two aspects:

1.  **Initial Load (Server-Side)**: Provide default meta tags in root templates like `app.blade.php`. This serves as a fallback when SSR is disabled or before Inertia has loaded.
2.  **Dynamic Updates (Client-Side)**: Use Inertia's `<Head>` component to dynamically update titles and meta tags on each page transition.

The recommended configuration is as follows:

- **Laravel (Blade)**: Set base meta tags (`viewport`, `charset`, default `og:image`, etc.).
- **Inertia (React/Vue)**: Create and use a reusable `SeoHead` component to manage page-specific `title`, `description`, `canonical`, and `og:*` tags.
- **Structure**: Embed structured data (JSON-LD) within the components.

## 2. Server-Side Setup

### 2.1 Root Template (`resources/views/app.blade.php`)

This is the foundation template for all pages. Set default values here, but prepare them to be overwritten by Inertia during page transitions.

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- 基本設定 (Inertiaが管理しない部分) -->
    <link rel="icon" href="/favicon.ico" />

    <!-- Inertiaによる動的制御用のプレースホルダー -->
    <!-- inertiaHead ディレクティブは必須 -->
    @inertiaHead

    <!-- フォールバック用メタタグ (任意: JS無効環境やクローラー用) -->
    @if(!isset($page))
    <title>{{ config('app.name') }}</title>
    <meta name="description" content="{{ config('app.description') }}" />
    @endif @viteReactRefresh @vite(['resources/js/app.tsx',
    "resources/js/pages/{$page['component']}.tsx"])
  </head>
  <body class="font-sans antialiased">
    @inertia
  </body>
</html>
```

### 2.2 Sharing Common Data (`HandleInertiaRequests.php`)

Share information commonly used across all pages (app name, current URL, default OGP image, etc.) as properties.

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'app' => [
            'name' => config('app.name'),
            'url' => config('app.url'),
            'description' => config('app.description', 'Default description here'),
        ],
        // 現在のURLを共有しておくとCanonical URL生成に便利
        'currentUrl' => $request->url(),
    ];
}
```

## 3. OGP/Bot Countermeasures in SPA Mode (without SSR)

**Important**: When not using SSR, social media bots (Twitter/X, LINE, Discord, etc.) do not execute JavaScript; they only analyze the initial HTML returned by the server.
Therefore, page-specific OGP (images and titles) will not be reflected using only Inertia's `<Head>` component (client-side).

To solve this, use the technique of **passing metadata directly from the Controller to the Blade template** (`withViewData`).

### 3.1 Implementation in Controller (`withViewData`)

For dynamic pages (like article details), pass data as follows:

```php
// PostController.php
use Inertia\Inertia;

public function show(Post $post)
{
    return Inertia::render('Posts/Show', [
        'post' => $post
    ])->withViewData([
        'metaTitle' => $post->title,
        'metaDescription' => Str::limit($post->body, 120),
        'metaImage' => $post->image_url,
    ]);
}
```

### 3.2 Modifying the Root Template (`app.blade.php`)

Modify the Blade template to use variables passed via `withViewData` if they exist, otherwise showing default values.

```html
<head>
  {{-- ... --}} @php $siteName = config('app.name'); $title = $metaTitle ??
  $siteName; $description = $metaDescription ?? config('app.description');
  $image = $metaImage ?? asset('og-image.png'); $url = url()->current(); @endphp
  {{-- 基本タグ (Bot/Crawler用) --}}
  <title inertia>{{ $title }}</title>
  <meta name="description" content="{{ $description }}" />

  {{-- OGP (Facebook/LINE/Discord) --}}
  <meta property="og:site_name" content="{{ $siteName }}" />
  <meta property="og:title" content="{{ $title }}" />
  <meta property="og:description" content="{{ $description }}" />
  <meta property="og:url" content="{{ $url }}" />
  <meta property="og:image" content="{{ $image }}" />
  <meta property="og:type" content="website" />

  {{-- Twitter Card --}}
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="{{ $title }}" />
  <meta name="twitter:description" content="{{ $description }}" />
  <meta name="twitter:image" content="{{ $image }}" />

  {{-- Inertia Head（クライアント遷移時に上書きされる） --}} @inertiaHead
</head>
```

With this setup, you achieve a hybrid behavior: **"Static Blade tags for bots"** and **"Dynamic Inertia updates for users."** This enables perfect SEO/OGP support even without SSR.

## 4. Client-Side Setup

### 4.1 Inertia App Configuration (`resources/js/app.tsx`)

By setting the `title` callback, when you use `<Head title="Page Name" />`, it will automatically be converted to the format `Page Name - App Name`.

```tsx
import { createInertiaApp } from '@inertiajs/react'

createInertiaApp({
  title: (title) =>
    title
      ? `${title} - ${import.meta.env.VITE_APP_NAME || 'Laravel'}`
      : 'Laravel',
  // ...
})
```

### 4.2 Reusable SeoHead Component (Recommended)

Listing several `meta` tags within each page's `<Head>` is difficult to manage, so create a dedicated component.
**Important**: By using the `head-key` property, Inertia prevents tag duplication and ensures correct overwriting during updates.

**File Path:** `resources/js/components/seo-head.tsx`

```tsx
import { Head, usePage } from '@inertiajs/react'

interface SeoProps {
  title?: string
  description?: string
  image?: string
  url?: string
  type?: 'website' | 'article'
}

export default function SeoHead({
  title,
  description,
  image,
  url,
  type = 'website',
}: SeoProps) {
  // 共有プロパティからデフォルト値を取得
  const { app, currentUrl } = usePage<any>().props

  const siteName = app.name
  const metaDescription = description || app.description
  const metaUrl = url || currentUrl
  const metaImage = image || `${app.url}/og-image.png` // デフォルトOGP画像

  // タイトルテンプレート（app.tsxの設定と整合させること）
  const pageTitle = title ? `${title} - ${siteName}` : siteName

  return (
    <Head title={title}>
      {/* Basic Meta - head-keyを使用して重複回避 */}
      <meta
        head-key="description"
        name="description"
        content={metaDescription}
      />
      <link rel="canonical" href={metaUrl} />

      {/* Open Graph - head-key必須 */}
      <meta head-key="og:url" property="og:url" content={metaUrl} />
      <meta head-key="og:type" property="og:type" content={type} />
      <meta
        head-key="og:site_name"
        property="og:site_name"
        content={siteName}
      />
      <meta head-key="og:title" property="og:title" content={pageTitle} />
      <meta
        head-key="og:description"
        property="og:description"
        content={metaDescription}
      />
      <meta head-key="og:image" property="og:image" content={metaImage} />

      {/* Twitter Card - head-key必須 */}
      <meta
        head-key="twitter:card"
        name="twitter:card"
        content="summary_large_image"
      />
      <meta head-key="twitter:title" name="twitter:title" content={pageTitle} />
      <meta
        head-key="twitter:description"
        name="twitter:description"
        content={metaDescription}
      />
      <meta head-key="twitter:image" name="twitter:image" content={metaImage} />
    </Head>
  )
}
```

### 4.3 Usage Example in Pages

```tsx
import SeoHead from '@/components/seo-head'

export default function AboutPage() {
  return (
    <>
      <SeoHead
        title="About Us"
        description="Learn more about our team and mission."
      />
      <main>
        <h1>About Us</h1>
        {/* ... */}
      </main>
    </>
  )
}
```

## 5. Structured Data (JSON-LD)

To support Google Rich Results, embed structured data as a `<script>` tag within the `<Head>` component.

```tsx
// 記事ページなどの例
const jsonLd = {
  '@context': 'https://schema.org',
  '@type': 'Article',
  headline: title,
  image: [image],
  datePublished: publishedAt,
  author: {
    '@type': 'Person',
    name: authorName,
  },
}

return (
  <Head>
    {/* ...他のSEOタグ... */}
    <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
  </Head>
)
```

## 6. Sitemaps & Robots

### 6.1 Sitemap.xml

The use of the `spatie/laravel-sitemap` package is recommended.

1.  **Installation**:

    ```bash
    composer require spatie/laravel-sitemap
    ```

2.  **Adding a Generation Route** (In `routes/console.php` or a dedicated command):
    It's not practical to manually update the sitemap every time content increases, so generate it via daily batch jobs or similar.

    ```php
    use Spatie\Sitemap\Sitemap;
    use Spatie\Sitemap\Tags\Url;

    Artisan::command('sitemap:generate', function () {
        $sitemap = Sitemap::create();

        // 静的ページ
        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add(Url::create('/about'));

        // 動的ページ（例: 記事）
        \App\Models\Post::all()->each(function ($post) use ($sitemap) {
            $sitemap->add(Url::create("/posts/{$post->slug}")
                ->setLastModificationDate($post->updated_at));
        });

        $sitemap->writeToDisk('public', 'sitemap.xml');
    })->purpose('Generate sitemap');
    ```

### 6.2 Robots.txt

By default, Laravel serves `public/robots.txt`, but you may want to change its content depending on the environment (Production vs. Staging).

**Countermeasure**: Dynamically serve it via `routes/web.php` or overwrite the file in the deployment flow. A route definition is simpler.

```php
// routes/web.php
Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";

    if (app()->environment('production')) {
        $content .= "Allow: /\n";
        $content .= "Sitemap: " . url('/sitemap.xml');
    } else {
        $content .= "Disallow: /"; // ステージング等はクロール禁止
    }

    return response($content, 200)
        ->header('Content-Type', 'text/plain');
});
```

_Note: You must delete the existing `public/robots.txt`._

## 7. Implementation Checklist

Verify the following after implementation:

- [ ] **Blade fallbacks**: Ensure `app.blade.php` has appropriate default values or values injected via `withViewData` for crawlers that don't support JS (e.g., SNS sharing).
- [ ] **Use of head-key**: Use `head-key` within Inertia's `<Head>` to prevent duplicate meta tags.
- [ ] **Canonical URL**: Ensure self-referencing canonical URLs are set to prevent duplicate content.
- [ ] **robots.txt / sitemap.xml**: Ensure crawler access control and guidance are appropriately configured.
- [ ] **Image paths**: Verify OGP images are resolved with absolute paths or correctly interpreted relative paths.
- [ ] **404 page**: Ensure a proper 404 status code is returned instead of a "soft 404".

## 8. Tool Verification

After implementation, verify using the following tools:

- **Google Search Console**: Check indexing status and structured data errors.
- **Facebook Sharing Debugger**: Clear OGP cache and verify appearance.
- **Twitter Card Validator**: Verify Twitter card appearance (Note: Preview functionality may be limited).
- **Chrome DevTools (Lighthouse)**: Measure SEO scores for each page.

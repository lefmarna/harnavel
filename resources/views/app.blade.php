<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    <style>
        html {
            background-color: oklch(1 0 0);
        }

        html.dark {
            background-color: oklch(0.145 0 0);
        }
    </style>

    @php
        $pageProps = isset($page['props']) && is_array($page['props']) ? $page['props'] : [];
        $pageSeo = isset($pageProps['seo']) && is_array($pageProps['seo']) ? $pageProps['seo'] : [];
        $displayName =
            is_string($pageProps['appDisplayName'] ?? null) && $pageProps['appDisplayName'] !== ''
                ? $pageProps['appDisplayName']
                : config('app.display_name');
        $seoTitle = $pageSeo['title'] ?? null;
        $robots = $pageSeo['robots'] ?? 'index, follow, max-video-preview:-1, max-image-preview:large, max-snippet:-1';

        $title = $seoTitle ? "{$seoTitle} - {$displayName}" : $displayName;
        $description = $pageSeo['description'] ?? ($pageProps['appDescription'] ?? config('app.description'));
        $rawImage = $pageSeo['image'] ?? 'og-image.png';
        $image = \Illuminate\Support\Str::startsWith($rawImage, ['http://', 'https://']) ? $rawImage : asset($rawImage);
        $rawUrl = $pageSeo['url'] ?? url()->current();
        $url = \Illuminate\Support\Str::startsWith($rawUrl, ['http://', 'https://']) ? $rawUrl : url($rawUrl);
        $type = $pageSeo['type'] ?? 'website';
        $locale = app()->getLocale() === 'ja' ? 'ja_JP' : 'en_US';
    @endphp

    <link rel="canonical" inertia="canonical" href="{{ $url }}">

    <title inertia>{{ $title }}</title>

    <meta inertia="description" name="description" content="{{ $description }}">
    <meta name="author" content="lefmarna">
    <meta inertia="robots" name="robots" content="{{ $robots }}">

    <meta inertia="og:type" property="og:type" content="{{ $type }}">
    <meta inertia="og:url" property="og:url" content="{{ $url }}">
    <meta inertia="og:title" property="og:title" content="{{ $title }}">
    <meta inertia="og:description" property="og:description" content="{{ $description }}">
    <meta inertia="og:image" property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{{ $locale }}">
    <meta inertia="og:site_name" property="og:site_name" content="{{ $displayName }}">

    <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
    <meta inertia="twitter:url" name="twitter:url" content="{{ $url }}">
    <meta name="twitter:site" content="@lefmarna">
    <meta name="twitter:creator" content="@lefmarna">
    <meta inertia="twitter:title" name="twitter:title" content="{{ $title }}">
    <meta inertia="twitter:description" name="twitter:description" content="{{ $description }}">
    <meta inertia="twitter:image" name="twitter:image" content="{{ $image }}">

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>

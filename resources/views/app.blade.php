<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        $defaultRobots = $pageProps['defaultRobots'] ?? null;
        $seoTitle = $pageSeo['title'] ?? null;
        $robots = $pageSeo['robots'] ?? $defaultRobots;
        $googlebot = $pageSeo['googlebot'] ?? 'max-image-preview:large';

        $title = $seoTitle ? "{$seoTitle} - {$displayName}" : $displayName;
        $description = $pageSeo['description'] ?? ($pageProps['appDescription'] ?? config('app.description'));
        $rawImage = $pageSeo['image'] ?? 'opengraph-image.png';
        $image = \Illuminate\Support\Str::startsWith($rawImage, ['http://', 'https://']) ? $rawImage : asset($rawImage);
        $rawUrl = $pageSeo['url'] ?? url()->current();
        $url = \Illuminate\Support\Str::startsWith($rawUrl, ['http://', 'https://']) ? $rawUrl : url($rawUrl);
        $type = $pageSeo['type'] ?? 'website';
        $locale = app()->getLocale() === 'ja' ? 'ja_JP' : 'en_US';
    @endphp

    <link rel="canonical" data-inertia="canonical" href="{{ $url }}">

    <title data-inertia>{{ $title }}</title>

    <meta data-inertia="description" name="description" content="{{ $description }}">
    <meta name="author" content="{{ config('app.author', '') }}">
    <meta data-inertia="format-detection" name="format-detection" content="telephone=no, email=no">
    @if (is_string($robots) && $robots !== '')
        <meta data-inertia="robots" name="robots" content="{{ $robots }}">
    @endif
    <meta data-inertia="googlebot" name="googlebot" content="{{ $googlebot }}">

    <meta data-inertia="og:type" property="og:type" content="{{ $type }}">
    <meta data-inertia="og:url" property="og:url" content="{{ $url }}">
    <meta data-inertia="og:title" property="og:title" content="{{ $title }}">
    <meta data-inertia="og:description" property="og:description" content="{{ $description }}">
    <meta data-inertia="og:image" property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{{ $locale }}">
    <meta data-inertia="og:site_name" property="og:site_name" content="{{ $displayName }}">

    <meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">
    <meta data-inertia="twitter:url" name="twitter:url" content="{{ $url }}">
    <meta name="twitter:site" content="@lefmarna">
    <meta name="twitter:creator" content="@lefmarna">
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ $title }}">
    <meta data-inertia="twitter:description" name="twitter:description" content="{{ $description }}">
    <meta data-inertia="twitter:image" name="twitter:image" content="{{ $image }}">

    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180" type="image/png">
    <link rel="manifest" href="/manifest.webmanifest">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    @inertiaHead

    @if ($tracking['adsEnabled'] ?? false)
        @include('partials.google-adsense')
    @endif
    @if ($tracking['trackingEnabled'] ?? false)
        @include('partials.google-analytics')
    @endif
</head>

<body class="font-sans">
    @inertia
    @include('partials.structured-data')
</body>

</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
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

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: oklch(1 0 0);
        }

        html.dark {
            background-color: oklch(0.145 0 0);
        }
    </style>

    @php
        $siteName = $page['props']['appDisplayName'] ?? 'Laravel';
        $seo = $page['props']['seo'] ?? [];
        $seoTitle = $seo['title'] ?? null;
        $title = $seoTitle ? $seoTitle . ' - ' . $siteName : $siteName;
        $description = $seo['description'] ?? ($page['props']['appDescription'] ?? '');
        $ogImage = asset('og-image.png');
    @endphp

    <title inertia>{{ $title }}</title>
    <meta inertia="description" name="description" content="{{ $description }}">
    <meta name="author" content="{{ config('app.author', '') }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta inertia="og:title" property="og:title" content="{{ $title }}">
    <meta inertia="og:description" property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta inertia="og:url" property="og:url" content="{{ url(strtok($page['url'], '?')) }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta inertia="twitter:title" name="twitter:title" content="{{ $title }}">
    <meta inertia="twitter:description" name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
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

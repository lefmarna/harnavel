@php
    $siteName = isset($displayName) && is_string($displayName) && $displayName !== ''
        ? $displayName
        : config('app.display_name', config('app.name', 'Laravel'));
    $siteDescription = isset($description) && is_string($description) && $description !== ''
        ? $description
        : config('app.description', '');
    $siteUrl = rtrim((string) config('app.url', url('/')), '/');
    $logoUrl = asset('logo.svg');
    $author = config('app.author');
    $inLanguage = str_replace('_', '-', app()->getLocale());

    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'description' => $siteDescription,
        'url' => $siteUrl,
        'inLanguage' => $inLanguage,
    ];

    $authorSchema = is_string($author) && $author !== '' ? [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $author,
        'url' => $siteUrl,
    ] : null;

    $webApplicationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => $siteName,
        'url' => $siteUrl,
        'operatingSystem' => 'Web Browser',
        'description' => $siteDescription,
        'image' => $logoUrl,
        'inLanguage' => $inLanguage,
        'creator' => $authorSchema ? [
            '@type' => 'Person',
            'name' => $authorSchema['name'],
        ] : null,
        'featureList' => [
            'Laravel 12 backend',
            'Inertia.js v2 and React 19 frontend',
            'Tailwind CSS v4 UI foundation',
            'Docker local development environment',
            'Authentication and application scaffolding',
        ],
    ];

    $schemaFilter = function (mixed $value): bool {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    };

    $websiteSchema = array_filter($websiteSchema, $schemaFilter);
    $webApplicationSchema = array_filter($webApplicationSchema, $schemaFilter);
@endphp

<script type="application/ld+json">
    {!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@if ($authorSchema)
    <script type="application/ld+json">
        {!! json_encode($authorSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endif
<script type="application/ld+json">
    {!! json_encode($webApplicationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

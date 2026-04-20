@php
    $siteName = isset($displayName) && is_string($displayName) && $displayName !== ''
        ? $displayName
        : config('app.display_name', config('app.name', 'Laravel'));
    $siteDescription = config('app.description', '');
    $siteUrl = rtrim((string) config('app.url', url('/')), '/');
    $logoUrl = asset('apple-touch-icon.png');
    $author = config('app.author');
    $inLanguage = str_replace('_', '-', app()->getLocale());
    $pageTitle = isset($title) && is_string($title) && $title !== ''
        ? $title
        : $siteName;
    $pageDescription = isset($description) && is_string($description) && $description !== ''
        ? $description
        : $siteDescription;
    $pageUrl = isset($url) && is_string($url) && $url !== ''
        ? $url
        : $siteUrl;
    $websiteId = $siteUrl.'/#website';
    $webApplicationId = $siteUrl.'/#webapplication';
    $webPageId = rtrim($pageUrl, '/').'#webpage';

    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $websiteId,
        'name' => $siteName,
        'description' => $siteDescription,
        'url' => $siteUrl,
        'inLanguage' => $inLanguage,
    ];

    $authorSchema = is_string($author) && $author !== '' ? [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        '@id' => $siteUrl.'/#author',
        'name' => $author,
        'url' => $siteUrl,
    ] : null;

    $webApplicationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        '@id' => $webApplicationId,
        'name' => $siteName,
        'url' => $siteUrl,
        'operatingSystem' => 'Web Browser',
        'description' => $siteDescription,
        'image' => $logoUrl,
        'inLanguage' => $inLanguage,
        'creator' => $authorSchema ? [
            '@id' => $authorSchema['@id'],
        ] : null,
        'featureList' => [
            'Laravel backend',
            'Inertia.js and React frontend',
            'Tailwind CSS v4 UI foundation',
            'Docker local development environment',
            'Authentication and application scaffolding',
        ],
    ];

    $webPageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $webPageId,
        'name' => $pageTitle,
        'description' => $pageDescription,
        'url' => $pageUrl,
        'inLanguage' => $inLanguage,
        'isPartOf' => [
            '@id' => $websiteId,
        ],
        'about' => [
            '@id' => $webApplicationId,
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
    $webPageSchema = array_filter($webPageSchema, $schemaFilter);
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
<script type="application/ld+json">
    {!! json_encode($webPageSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

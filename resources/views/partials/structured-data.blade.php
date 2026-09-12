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

    /**
     * JSON-LD を <script> 要素の内側へ直接埋め込むため、`<` `>` `&` `\'` `"` を
     * \uXXXX へ退避する。タイトルと説明はアプリが自由に流し込む枠で、ユーザー入力や
     * 生成物が届く経路を持つアプリもある。これが無いと `</script>` で script 要素を
     * 脱出できてしまう。
     *
     * `/` の退避（json_encode の既定）も外さない。`<` を退避していれば `</script>` は
     * 作れないが、二重に塞いでおけば片方を落としたときに穴が開かない。ここは
     * Laravel が HTML へ JSON を埋めるときの既定（Illuminate\Support\Js）と同じ構成で、
     * 読み手のために足すのは日本語をそのまま出す UNESCAPED_UNICODE だけ。
     */
    $encodeSchema = fn (array $schema): string => json_encode(
        $schema,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    );
@endphp

<script type="application/ld+json">
    {!! $encodeSchema($websiteSchema) !!}
</script>
@if ($authorSchema)
    <script type="application/ld+json">
        {!! $encodeSchema($authorSchema) !!}
    </script>
@endif
<script type="application/ld+json">
    {!! $encodeSchema($webApplicationSchema) !!}
</script>
<script type="application/ld+json">
    {!! $encodeSchema($webPageSchema) !!}
</script>

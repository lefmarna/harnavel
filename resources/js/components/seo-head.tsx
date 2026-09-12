import { Head, usePage } from "@inertiajs/react";

import type { SeoData, SharedData } from "@/types";

interface SeoProps {
  title?: string;
  description?: string;
  robots?: string;
  googlebot?: string;
  image?: string;
  imageAlt?: string;
  url?: string;
  type?: "website" | "article";
}

function imageMimeType(imageUrl: string): string | undefined {
  const extension = new URL(imageUrl).pathname.split(".").pop()?.toLowerCase();

  if (extension === undefined) {
    return undefined;
  }

  switch (extension) {
    case "jpg":
    case "jpeg":
      return "image/jpeg";
    case "png":
      return "image/png";
    case "webp":
      return "image/webp";
    case "gif":
      return "image/gif";
    default:
      return undefined;
  }
}

export default function SeoHead({
  title,
  description,
  robots,
  googlebot,
  image,
  imageAlt,
  url,
  type,
}: SeoProps) {
  const {
    seo,
    name,
    appDisplayName,
    appDescription,
    defaultRobots,
    url: appUrl,
    currentUrl,
  } = usePage<SharedData>().props;
  const sharedSeo: SeoData = seo;
  const siteName = appDisplayName || name;
  const resolvedTitle = title ?? sharedSeo.title;
  const metaType = type ?? sharedSeo.type ?? "website";
  const metaDescription = description ?? sharedSeo.description ?? appDescription ?? "";
  const metaRobots = robots ?? sharedSeo.robots ?? defaultRobots ?? undefined;
  const metaGooglebot = googlebot ?? sharedSeo.googlebot ?? "max-image-preview:large";
  const metaUrl = new URL(url ?? sharedSeo.url ?? currentUrl, appUrl).toString();
  const metaImage = new URL(image ?? sharedSeo.image ?? "/opengraph-image.jpg", appUrl).toString();
  const metaImageAlt = imageAlt ?? sharedSeo.imageAlt ?? siteName;
  const metaImageType = imageMimeType(metaImage);
  const pageTitle = resolvedTitle ? `${resolvedTitle} - ${siteName}` : siteName;

  return (
    <Head title={resolvedTitle}>
      <meta head-key="description" name="description" content={metaDescription} />
      {metaRobots ? <meta head-key="robots" name="robots" content={metaRobots} /> : null}
      <meta head-key="googlebot" name="googlebot" content={metaGooglebot} />
      <meta head-key="format-detection" name="format-detection" content="telephone=no, email=no" />
      <link head-key="canonical" rel="canonical" href={metaUrl} />

      <meta head-key="og:url" property="og:url" content={metaUrl} />
      <meta head-key="og:type" property="og:type" content={metaType} />
      <meta head-key="og:site_name" property="og:site_name" content={siteName} />
      <meta head-key="og:title" property="og:title" content={pageTitle} />
      <meta head-key="og:description" property="og:description" content={metaDescription} />
      <meta head-key="og:image" property="og:image" content={metaImage} />
      {metaImageType ? (
        <meta head-key="og:image:type" property="og:image:type" content={metaImageType} />
      ) : null}
      <meta head-key="og:image:alt" property="og:image:alt" content={metaImageAlt} />

      <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
      <meta head-key="twitter:url" name="twitter:url" content={metaUrl} />
      <meta head-key="twitter:title" name="twitter:title" content={pageTitle} />
      <meta head-key="twitter:description" name="twitter:description" content={metaDescription} />
      <meta head-key="twitter:image" name="twitter:image" content={metaImage} />
      <meta head-key="twitter:image:alt" name="twitter:image:alt" content={metaImageAlt} />
    </Head>
  );
}

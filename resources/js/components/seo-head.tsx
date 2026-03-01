import { Head, usePage } from '@inertiajs/react'
import type { SharedData } from '@/types'

export default function SeoHead() {
  const { props, url } = usePage<SharedData>()
  const seo = props.seo ?? {}
  const appName = props.appDisplayName || 'Laravel'

  const pageTitle = seo.title ?? ''
  const formattedTitle = pageTitle ? `${pageTitle} - ${appName}` : appName
  const finalDescription = seo.description ?? (props.appDescription || '')
  const pathOnly = url ? url.split('?')[0] : window.location.pathname
  const canonicalUrl = `${window.location.origin}${pathOnly}`

  return (
    <Head title={formattedTitle}>
      <meta head-key="og:title" property="og:title" content={formattedTitle} />
      <meta
        head-key="twitter:title"
        name="twitter:title"
        content={formattedTitle}
      />
      <meta head-key="og:url" property="og:url" content={canonicalUrl} />
      <meta
        head-key="description"
        name="description"
        content={finalDescription}
      />
      <meta
        head-key="og:description"
        property="og:description"
        content={finalDescription}
      />
      <meta
        head-key="twitter:description"
        name="twitter:description"
        content={finalDescription}
      />
    </Head>
  )
}

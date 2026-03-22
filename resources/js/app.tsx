import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { type ComponentType, type ReactNode, StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import SeoHead from './components/seo-head'
import '../css/app.css'
import { initializeTheme } from './hooks/use-appearance'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel App Template'

type LayoutComponent = ComponentType<{
  children: ReactNode
  [key: string]: unknown
}>

type LayoutFunction = (children: ReactNode) => ReactNode

type PageComponent = ComponentType & {
  layout?: LayoutFunction | LayoutComponent[]
  originalLayout?: LayoutFunction | LayoutComponent[]
  seoWrapped?: boolean
}

type PageModule = {
  default: PageComponent
}

function SeoLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <SeoHead />
      {children}
    </>
  )
}

const wrapLayoutWithSeo = (
  layout?: LayoutFunction | LayoutComponent[],
): LayoutFunction | LayoutComponent[] => {
  if (Array.isArray(layout)) {
    return [SeoLayout, ...layout]
  }

  return (children: ReactNode) => {
    const content = typeof layout === 'function' ? layout(children) : children

    return <SeoLayout>{content}</SeoLayout>
  }
}

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent<PageModule>(
      `./pages/${name}.tsx`,
      import.meta.glob<PageModule>('./pages/**/*.tsx'),
    ).then((module) => {
      const page = module.default

      if (!page.seoWrapped) {
        page.originalLayout = page.layout
        page.layout = wrapLayoutWithSeo(page.originalLayout)
        page.seoWrapped = true
      }

      return module
    }),
  setup({ el, App, props }) {
    const root = createRoot(el)

    root.render(
      <StrictMode>
        <App {...props} />
      </StrictMode>,
    )
  },
  progress: {
    color: '#4B5563',
  },
})

// This will set light / dark mode on load...
initializeTheme()

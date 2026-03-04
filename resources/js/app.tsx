import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { type ComponentType, type ReactNode, StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import SeoHead from './components/seo-head'
import '../css/app.css'
import { initializeTheme } from './hooks/use-appearance'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel App Template'

type PageComponent = ComponentType & {
  layout?: (children: ReactNode) => ReactNode
  originalLayout?: (children: ReactNode) => ReactNode
  seoWrapped?: boolean
}

type PageModule = {
  default: PageComponent
}

const withSeoLayout = (children: ReactNode) => (
  <>
    <SeoHead />
    {children}
  </>
)

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob('./pages/**/*.tsx'),
    ).then((module) => {
      const page = (module as PageModule).default

      if (!page.seoWrapped) {
        page.originalLayout = page.layout
        page.layout = (children: ReactNode) => {
          const content = page.originalLayout
            ? page.originalLayout(children)
            : children

          return withSeoLayout(content)
        }
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

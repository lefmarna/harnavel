import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { type ReactNode, StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import SeoHead from './components/seo-head'
import '../css/app.css'
import { initializeTheme } from './hooks/use-appearance'

const seoLayout = (children: ReactNode) => (
  <>
    <SeoHead />
    {children}
  </>
)

createInertiaApp({
  resolve: (name) => {
    const page = resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob('./pages/**/*.tsx'),
    ) as Promise<{
      default: { layout?: (page: ReactNode) => ReactNode }
    }>

    page.then((module) => {
      if (!module.default.layout) {
        module.default.layout = seoLayout
      }
    })

    return page
  },
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

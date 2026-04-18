import { createInertiaApp } from '@inertiajs/react'
import type { ReactNode } from 'react'
import { GoogleAnalyticsTracker } from './components/functional'
import SeoHead from './components/seo-head'
import '../css/app.css'
import { initializeTheme } from './hooks/use-appearance'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel App Template'

function SeoWrapper({ children }: { children: ReactNode }) {
  return (
    <>
      <SeoHead />
      <GoogleAnalyticsTracker />
      {children}
    </>
  )
}

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: () => SeoWrapper,
  strictMode: true,
  progress: {
    color: '#4B5563',
  },
})

// This will set light / dark mode on load...
initializeTheme()

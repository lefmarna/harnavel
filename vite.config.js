import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'

const isGitHubActions = process.env.GITHUB_ACTIONS === 'true'

const wayfinderCommand = isGitHubActions
  ? 'php artisan wayfinder:generate --with-form'
  : 'docker compose exec -T app php artisan wayfinder:generate'

export default defineConfig({
  server: {
    port: 5173,
    strictPort: true,
  },
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
    }),
    react({
      babel: {
        plugins: ['babel-plugin-react-compiler'],
      },
    }),
    tailwindcss(),
    wayfinder({
      formVariants: true,
      command: wayfinderCommand,
    }),
  ],
  esbuild: {
    jsx: 'automatic',
  },
})

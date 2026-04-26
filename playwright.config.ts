import fs from 'node:fs'

import { defineConfig, devices } from '@playwright/test'

const isCI = !!process.env.CI
const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : ''
const parseEnvPort = (name: string, fallback: number): number => {
  const value =
    process.env[name] ?? envFile.match(new RegExp(`^${name}=(\\d+)$`, 'm'))?.[1]
  const port = Number.parseInt(value ?? '', 10)

  return Number.isNaN(port) ? fallback : port
}
const localPort = parseEnvPort('NGINX_PORT', 8080)
const PORT = isCI ? 8000 : localPort
const VITE_PORT = parseEnvPort('VITE_PORT', 5173)

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 2 : 0,
  workers: isCI ? 1 : undefined,
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ['json', { outputFile: 'playwright-report/results.json' }],
  ],
  use: {
    baseURL: `http://localhost:${PORT}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],

  webServer: isCI
    ? {
        command: 'php artisan serve',
        url: `http://localhost:${PORT}`,
        reuseExistingServer: false,
      }
    : {
        command: 'pnpm dev',
        url: `http://localhost:${VITE_PORT}/@vite/client`,
        reuseExistingServer: true,
      },
})

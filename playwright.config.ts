import fs from 'node:fs'

import { defineConfig, devices } from '@playwright/test'

const isCI = !!process.env.CI
const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : ''
const envPort = envFile.match(/^NGINX_PORT=(\d+)$/m)?.[1] ?? '8080'
const localPort = Number.parseInt(envPort, 10)
const PORT = isCI ? 8000 : (Number.isNaN(localPort) ? 8080 : localPort)

export default defineConfig({
  testDir: './e2e',
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

  webServer: {
    command: isCI ? 'php artisan serve' : 'pnpm dev',
    url: `http://localhost:${PORT}`,
    reuseExistingServer: !isCI,
  },
})

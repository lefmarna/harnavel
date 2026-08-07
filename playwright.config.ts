import fs from 'node:fs'

import { defineConfig, devices } from '@playwright/test'

const isCI = !!process.env.CI
const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : ''
const parseEnvPort = (name: string, fallback?: number): number => {
  const value =
    process.env[name] ?? envFile.match(new RegExp(`^${name}=(\\d+)$`, 'm'))?.[1]
  const port = Number.parseInt(value ?? '', 10)

  if (Number.isNaN(port)) {
    if (fallback === undefined) {
      throw new Error(
        `.env に ${name} を追加してください（.env.example 参照）。`,
      )
    }

    return fallback
  }

  return port
}

const CONTAINER_E2E_PORT = 8001
const PORT = isCI ? 8000 : parseEnvPort('NGINX_PORT') + 1000
const VITE_PORT = parseEnvPort('VITE_PORT', 5173)
const E2E_APP_URL = `http://localhost:${PORT}`
const E2E_READY_URL = `${E2E_APP_URL}/up`
const E2E_CONTAINER_NAME = `playwright-e2e-${PORT}-${process.pid}`

process.env.PLAYWRIGHT_E2E_CONTAINER = E2E_CONTAINER_NAME

export default defineConfig({
  testDir: './e2e/tests',
  globalSetup: './e2e/global-setup.ts',
  globalTeardown: './e2e/global-teardown.ts',
  forbidOnly: isCI,
  retries: 0,
  workers: 1,
  reporter: isCI
    ? [
        ['github'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
        ['json', { outputFile: 'playwright-report/results.json' }],
      ]
    : [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
        ['json', { outputFile: 'playwright-report/results.json' }],
      ],
  use: {
    baseURL: E2E_APP_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
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
        command: `BOOST_BROWSER_LOGS_WATCHER=false php artisan serve --no-reload --port=${PORT}`,
        url: E2E_READY_URL,
        reuseExistingServer: false,
      }
    : [
        {
          command: 'pnpm dev',
          url: `http://localhost:${VITE_PORT}/@vite/client`,
          reuseExistingServer: true,
        },
        {
          command:
            `docker compose run --rm --no-deps -T --name ${E2E_CONTAINER_NAME}` +
            ` -p 127.0.0.1:${PORT}:${CONTAINER_E2E_PORT}` +
            ` -e APP_URL=${E2E_APP_URL}` +
            ` -e BOOST_BROWSER_LOGS_WATCHER=false` +
            ' -e DB_HOST=mariadb-e2e -e DB_DATABASE=app_e2e' +
            ` app php artisan serve --no-reload --host=0.0.0.0 --port=${CONTAINER_E2E_PORT}`,
          url: E2E_READY_URL,
          reuseExistingServer: false,
          gracefulShutdown: {
            signal: 'SIGTERM',
            timeout: 5_000,
          },
        },
      ],
})

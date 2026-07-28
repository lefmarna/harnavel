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
        `.env に ${name} を追加してください（規約: NGINX_PORT + 1000、.env.example 参照）。`,
      )
    }

    return fallback
  }

  return port
}
// E2E は専用アプリ環境（.env.e2e / APP_ENV=e2e）で動かす。E2E_PORT はアプリごとに固定。
const CONTAINER_E2E_PORT = 8001
const PORT = isCI ? 8000 : parseEnvPort('E2E_PORT')
const VITE_PORT = parseEnvPort('VITE_PORT', 5173)
const E2E_APP_URL = `http://localhost:${PORT}`
// webServer の待機は globalSetup より先なので、DB 非依存の /up を見る。
const E2E_READY_URL = `${E2E_APP_URL}/up`

export default defineConfig({
  // spec だけを収集する。e2e/ 直下の node:test ファイルは pnpm test:unit 側。
  testDir: './e2e/tests',
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
    baseURL: E2E_APP_URL,
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
        command: `php artisan serve --port=${PORT}`,
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
          // APP_URL は E2E_PORT から導出する。.env.e2e には持たせない。
          command:
            `docker compose exec -T -e APP_ENV=e2e -e APP_URL=${E2E_APP_URL}` +
            ` app php artisan serve --host=0.0.0.0 --port=${CONTAINER_E2E_PORT}`,
          url: E2E_READY_URL,
          reuseExistingServer: true,
        },
      ],
})

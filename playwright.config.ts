import { defineConfig, devices } from "@playwright/test";

import { envPort, readLocalEnv } from "./scripts/local-env";

const isCI = !!process.env.CI;
const repoRoot = import.meta.dirname;
const env = readLocalEnv(repoRoot);

const CONTAINER_E2E_PORT = 8001;
const PORT = isCI ? 8000 : envPort(env, "NGINX_PORT") + 5;
const VITE_PORT = envPort(env, "VITE_PORT");
const E2E_APP_URL = `http://localhost:${PORT}`;
const E2E_READY_URL = `${E2E_APP_URL}/up`;
const E2E_CONTAINER_NAME = `playwright-e2e-${PORT}-${process.pid}`;

process.env.PLAYWRIGHT_E2E_CONTAINER = E2E_CONTAINER_NAME;

export default defineConfig({
  testDir: "./e2e/tests",
  globalSetup: "./e2e/global-setup.ts",
  globalTeardown: "./e2e/global-teardown.ts",
  forbidOnly: isCI,
  retries: 0,
  workers: 1,
  reporter: isCI
    ? [
        ["github"],
        ["html", { open: "never", outputFolder: "playwright-report" }],
        ["json", { outputFile: "playwright-report/results.json" }],
      ]
    : [
        ["list"],
        ["html", { open: "never", outputFolder: "playwright-report" }],
        ["json", { outputFile: "playwright-report/results.json" }],
      ],
  use: {
    baseURL: E2E_APP_URL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },

  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "firefox",
      use: { ...devices["Desktop Firefox"] },
    },
    {
      name: "webkit",
      use: { ...devices["Desktop Safari"] },
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
          command: "pnpm dev",
          url: `http://localhost:${VITE_PORT}/@vite/client`,
          reuseExistingServer: true,
          // 強制終了では public/hot が残り、停止後もアプリが dev server を参照し続ける。
          gracefulShutdown: {
            signal: "SIGTERM",
            timeout: 5_000,
          },
        },
        {
          command:
            `docker compose run --rm --no-deps -T --name ${E2E_CONTAINER_NAME}` +
            ` -p 127.0.0.1:${PORT}:${CONTAINER_E2E_PORT}` +
            ` -e APP_URL=${E2E_APP_URL}` +
            ` app scripts/run-e2e-artisan.sh serve --no-reload --host=0.0.0.0 --port=${CONTAINER_E2E_PORT}`,
          url: E2E_READY_URL,
          reuseExistingServer: false,
          gracefulShutdown: {
            signal: "SIGTERM",
            timeout: 5_000,
          },
        },
      ],
});

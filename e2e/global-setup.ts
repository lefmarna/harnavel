import fs from 'node:fs'

const isCI = !!process.env.CI
const envFile = fs.existsSync('.env') ? fs.readFileSync('.env', 'utf8') : ''

const parseEnvPort = (name: string, fallback: number): number => {
  const value =
    process.env[name] ?? envFile.match(new RegExp(`^${name}=(\\d+)$`, 'm'))?.[1]
  const port = Number.parseInt(value ?? '', 10)

  return Number.isNaN(port) ? fallback : port
}

const PORT = isCI ? 8000 : parseEnvPort('NGINX_PORT', 8080)
const LARAVEL_URL = `http://localhost:${PORT}`

async function waitForLaravel(timeoutMs = 60_000): Promise<void> {
  const startedAt = Date.now()

  while (Date.now() - startedAt < timeoutMs) {
    try {
      const response = await fetch(LARAVEL_URL)

      if (response.status < 500) {
        return
      }
    } catch {
      // Retry until Docker/nginx or artisan serve becomes reachable.
    }

    await new Promise((resolve) => setTimeout(resolve, 500))
  }

  throw new Error(`Laravel app did not become reachable at ${LARAVEL_URL}`)
}

export default async function globalSetup(): Promise<void> {
  await waitForLaravel()
}

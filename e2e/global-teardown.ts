import { spawnSync } from 'node:child_process'

export default function globalTeardown(): void {
  if (process.env.CI) {
    return
  }

  const container = process.env.PLAYWRIGHT_E2E_CONTAINER

  if (!container || !/^playwright-e2e-\d+-\d+$/.test(container)) {
    throw new Error('Playwright E2E container name is missing or invalid.')
  }

  const result = spawnSync('docker', ['rm', '--force', container], {
    encoding: 'utf8',
  })

  if (
    result.status !== 0 &&
    !result.stderr.includes(`No such container: ${container}`)
  ) {
    throw new Error(
      `E2E server cleanup failed: ${result.stderr || result.stdout}`,
    )
  }
}

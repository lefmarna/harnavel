import { execFileSync } from 'node:child_process'
import { setTimeout as sleep } from 'node:timers/promises'

import { assertE2eTarget } from './e2e-database-target'
import type { DatabaseTarget } from './e2e-database-target'

function artisan(args: string[], stdio: 'inherit' | 'pipe'): string {
  return execFileSync(
    'docker',
    [
      'compose',
      'exec',
      '-T',
      '-e',
      'DB_HOST=mariadb-e2e',
      '-e',
      'DB_DATABASE=app_e2e',
      'app',
      'php',
      'artisan',
      ...args,
    ],
    { encoding: 'utf8', stdio },
  )
}

function resolvedTarget(): DatabaseTarget {
  const output = artisan(
    [
      'tinker',
      '--execute',
      '$c = DB::connection();' +
        'echo \'TARGET:\'.json_encode(["host" => $c->getConfig("host"),' +
        ' "database" => $c->getConfig("database")]);',
    ],
    'pipe',
  )
  const probe = /TARGET:(\{.*?\})/.exec(output)?.[1]

  if (probe === undefined) {
    throw new Error(`E2E の接続先を確認できませんでした:\n${output}`)
  }

  return JSON.parse(probe) as DatabaseTarget
}

async function waitForDatabase(timeoutMs = 60_000): Promise<void> {
  const startedAt = Date.now()

  for (;;) {
    try {
      artisan(['tinker', '--execute', 'DB::connection()->getPdo();'], 'pipe')

      return
    } catch (error) {
      if (Date.now() - startedAt > timeoutMs) {
        throw new Error(
          `E2E データベースへ接続できません。mariadb-e2e の起動を確認してください。\n${String(error)}`,
        )
      }

      await sleep(500)
    }
  }
}

export default async function globalSetup(): Promise<void> {
  if (process.env.CI) {
    return
  }

  assertE2eTarget(resolvedTarget())
  await waitForDatabase()
  artisan(['migrate:fresh', '--force', '--seed'], 'inherit')
}

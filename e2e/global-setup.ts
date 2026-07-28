import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
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
      'APP_ENV=e2e',
      'app',
      'php',
      'artisan',
      ...args,
    ],
    { encoding: 'utf8', stdio },
  )
}

// DB_URL は接続生成時に host / database を上書きするので、生の config ではなく
// 接続が実際に使う値を読む。PDO は張らないため DB 停止中でも判定できる。
function resolvedTarget(): DatabaseTarget {
  const output = artisan(
    [
      'tinker',
      '--execute',
      '$c = DB::connection();' +
        'echo \'TARGET:\'.json_encode(["environment" => app()->environment(),' +
        ' "host" => $c->getConfig("host"), "database" => $c->getConfig("database")]);',
    ],
    'pipe',
  )
  const probe = /TARGET:(\{.*?\})/.exec(output)?.[1]

  if (probe === undefined) {
    throw new Error(`E2E の接続先を確認できませんでした:\n${output}`)
  }

  return JSON.parse(probe) as DatabaseTarget
}

// mariadb-e2e を新規起動した直後は初期化が終わっていない。接続先を検証したあとに待つ。
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
    // 使い捨ての runner 上で workflow がマイグレーション済み。
    return
  }

  if (!fs.existsSync('.env.e2e')) {
    throw new Error(
      '.env.e2e がありません。`mise run setup:e2e` を実行してください。',
    )
  }

  assertE2eTarget(resolvedTarget())
  await waitForDatabase()
  // ヘルパールートは作ったユーザーを消さないので、実行のたびに作り直す。
  artisan(['migrate:fresh', '--force'], 'inherit')
}

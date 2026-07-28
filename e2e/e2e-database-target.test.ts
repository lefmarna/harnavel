import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { assertE2eTarget, E2E_TARGET } from './e2e-database-target'
import type { DatabaseTarget } from './e2e-database-target'

describe('E2Eデータベース接続先のガード', () => {
  it('E2E専用の環境・ホスト・データベースが揃っていれば通す', () => {
    assert.doesNotThrow(() => assertE2eTarget({ ...E2E_TARGET }))
  })

  const rejected: Record<string, DatabaseTarget> = {
    通常開発DB: {
      environment: 'e2e',
      host: 'mariadb',
      database: 'app',
    },
    本番など第三のDB: {
      environment: 'e2e',
      host: 'production-host',
      database: 'production-db',
    },
    ホストだけE2Eでない: {
      environment: 'e2e',
      host: 'mariadb',
      database: 'app_e2e',
    },
    データベース名だけE2Eでない: {
      environment: 'e2e',
      host: 'mariadb-e2e',
      database: 'app',
    },
    APP_ENVがe2eでない: {
      environment: 'local',
      host: 'mariadb-e2e',
      database: 'app_e2e',
    },
  }

  for (const [name, target] of Object.entries(rejected)) {
    it(`${name}なら中止する`, () => {
      assert.throws(() => assertE2eTarget(target), /中止しました/)
    })
  }
})

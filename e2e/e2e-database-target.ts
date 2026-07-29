export const E2E_TARGET = {
  host: 'mariadb-e2e',
  database: 'app_e2e',
} as const

export type DatabaseTarget = {
  host: string
  database: string
}

export function assertE2eTarget(resolved: DatabaseTarget): void {
  const mismatched = (
    Object.keys(E2E_TARGET) as (keyof typeof E2E_TARGET)[]
  ).filter((key) => resolved[key] !== E2E_TARGET[key])

  if (mismatched.length === 0) {
    return
  }

  const describe = (target: DatabaseTarget): string =>
    `${target.host} / ${target.database}`

  throw new Error(
    `E2E の接続先が ${describe(E2E_TARGET)} ではなく ${describe(resolved)} です` +
      `（不一致: ${mismatched.join(', ')}）。migrate:fresh はデータを消すため中止しました。` +
      ' .env の DB_URL とPlaywrightのDB上書きを確認してください。',
  )
}

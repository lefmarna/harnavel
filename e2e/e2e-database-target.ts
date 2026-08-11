export const E2E_TARGET = {
  host: "mariadb-e2e",
  database: "app_e2e",
} as const;

export const CI_E2E_TARGET = {
  host: "127.0.0.1",
  database: "app_e2e",
} as const;

export type DatabaseTarget = {
  host: string;
  database: string;
};

export function assertE2eTarget(
  resolved: DatabaseTarget,
  expected: DatabaseTarget = E2E_TARGET,
): void {
  const mismatched = (Object.keys(expected) as (keyof DatabaseTarget)[]).filter(
    (key) => resolved[key] !== expected[key],
  );

  if (mismatched.length === 0) {
    return;
  }

  const describe = (target: DatabaseTarget): string => `${target.host} / ${target.database}`;

  throw new Error(
    `E2E の接続先が ${describe(expected)} ではなく ${describe(resolved)} です` +
      `（不一致: ${mismatched.join(", ")}）。migrate:fresh はデータを消すため中止しました。` +
      " .env の DB_URL とPlaywrightのDB上書きを確認してください。",
  );
}

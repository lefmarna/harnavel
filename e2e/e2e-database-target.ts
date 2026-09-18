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

const TARGET_KEYS: readonly (keyof DatabaseTarget)[] = ["host", "database"];

const describeTarget = (target: DatabaseTarget): string => `${target.host} / ${target.database}`;

export function isDatabaseTarget(value: unknown): value is DatabaseTarget {
  return (
    typeof value === "object" &&
    value !== null &&
    "host" in value &&
    typeof value.host === "string" &&
    "database" in value &&
    typeof value.database === "string"
  );
}

export function assertE2eTarget(
  resolved: DatabaseTarget,
  expected: DatabaseTarget = E2E_TARGET,
): void {
  const mismatched = TARGET_KEYS.filter((key) => resolved[key] !== expected[key]);

  if (mismatched.length === 0) {
    return;
  }

  throw new Error(
    `E2E の接続先が ${describeTarget(expected)} ではなく ${describeTarget(resolved)} です` +
      `（不一致: ${mismatched.join(", ")}）。migrate:fresh はデータを消すため中止しました。` +
      " .env の DB_URL とPlaywrightのDB上書きを確認してください。",
  );
}

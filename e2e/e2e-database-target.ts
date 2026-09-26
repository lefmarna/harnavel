import { readLocalEnv } from "../scripts/local-env";

export function localE2eTarget(root: string): DatabaseTarget {
  // compose.yaml が E2E サーバーへ渡す E2E_DB_DATABASE と同じ規則で期待値を作る。
  const env = readLocalEnv(root);
  if (!env.DEV_INFRA_PROJECT || !env.DB_DATABASE) {
    throw new Error("Worktree 専用 E2E DB が未設定です。mise run setup を実行してください。");
  }
  return { host: "mariadb", database: `${env.DB_DATABASE}_e2e` };
}

export const CI_E2E_TARGET = {
  host: "127.0.0.1",
  database: "app_e2e",
} as const;

export type DatabaseTarget = {
  host: string;
  database: string;
};

const TARGET_KEYS: readonly (keyof DatabaseTarget)[] = ["host", "database"];

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

export function assertE2eTarget(resolved: DatabaseTarget, expected: DatabaseTarget): void {
  const mismatched = TARGET_KEYS.filter((key) => resolved[key] !== expected[key]);

  if (mismatched.length === 0) {
    return;
  }

  const describeTarget = (target: DatabaseTarget): string => `${target.host} / ${target.database}`;

  throw new Error(
    `E2E の接続先が ${describeTarget(expected)} ではなく ${describeTarget(resolved)} です` +
      `（不一致: ${mismatched.join(", ")}）。migrate:fresh はデータを消すため中止しました。` +
      " .env の DB_URL とPlaywrightのDB上書きを確認してください。",
  );
}

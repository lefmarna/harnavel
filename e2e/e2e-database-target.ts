import { readLocalEnv } from "../scripts/local-env";

export type DatabaseTarget = {
  host: string;
  database: string;
};

export const CI_E2E_TARGET: DatabaseTarget = {
  host: "127.0.0.1",
  database: "app_e2e",
};

export function localE2eTarget(root: string): DatabaseTarget {
  // compose.yaml が E2E サーバーへ渡す E2E_DB_DATABASE と同じ規則で期待値を作る。
  const env = readLocalEnv(root);
  if (!env.DEV_INFRA_PROJECT || !env.DB_DATABASE) {
    throw new Error("Worktree 専用 E2E DB が未設定です。mise run setup を実行してください。");
  }

  return { host: "mariadb", database: `${env.DB_DATABASE}_e2e` };
}

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
  if (resolved.host === expected.host && resolved.database === expected.database) {
    return;
  }

  throw new Error(
    `E2E の接続先が ${expected.host} / ${expected.database} ではなく ${resolved.host} / ${resolved.database} です。` +
      "migrate:fresh はデータを消すため中止しました。.env の DB_URL と Playwright の DB 上書きを確認してください。",
  );
}

import path from "node:path";
import { setTimeout as sleep } from "node:timers/promises";

import {
  assertE2eTarget,
  CI_E2E_TARGET,
  localE2eTarget,
  isDatabaseTarget,
} from "./e2e-database-target";
import type { DatabaseTarget } from "./e2e-database-target";
import { runArtisan } from "./support/artisan";
import { assertViteDevAssets } from "./vite-dev-assets";

const REPO_ROOT = path.resolve(import.meta.dirname, "..");

function resolvedTarget(): DatabaseTarget {
  const output = runArtisan([
    "tinker",
    "--execute",
    "$c = DB::connection();" +
      'echo \'TARGET:\'.json_encode(["host" => $c->getConfig("host"),' +
      ' "database" => $c->getConfig("database")]);',
  ]);
  const probe = /TARGET:(\{.*?\})/.exec(output)?.[1];

  if (probe === undefined) {
    throw new Error(`E2E の接続先を確認できませんでした:\n${output}`);
  }

  const target: unknown = JSON.parse(probe);

  if (!isDatabaseTarget(target)) {
    throw new Error(`E2E の接続先の形式が想定と異なります:\n${probe}`);
  }

  return target;
}

async function waitForDatabase(timeoutMs = 60_000): Promise<void> {
  const startedAt = Date.now();

  for (;;) {
    try {
      runArtisan(["tinker", "--execute", "DB::connection()->getPdo();"]);

      return;
    } catch (error) {
      if (Date.now() - startedAt > timeoutMs) {
        throw new Error("E2E データベースへ接続できません。接続先と起動状態を確認してください。", {
          cause: error,
        });
      }

      await sleep(500);
    }
  }
}

export default async function globalSetup(): Promise<void> {
  const isCI = Boolean(process.env.CI);

  if (!isCI) {
    await assertViteDevAssets(REPO_ROOT);
  }

  assertE2eTarget(resolvedTarget(), isCI ? CI_E2E_TARGET : localE2eTarget(REPO_ROOT));
  await waitForDatabase();
  runArtisan(["migrate:fresh", "--force", "--seed"], "inherit");
}

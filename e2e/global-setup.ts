import { execFileSync } from "node:child_process";
import { setTimeout as sleep } from "node:timers/promises";

import { assertE2eTarget, CI_E2E_TARGET, E2E_TARGET } from "./e2e-database-target";
import type { DatabaseTarget } from "./e2e-database-target";

export type ArtisanRunner = (args: string[], stdio: "inherit" | "pipe") => string;

const dockerArtisan: ArtisanRunner = (args, stdio) => {
  const container = process.env.PLAYWRIGHT_E2E_CONTAINER;

  if (!container || !/^playwright-e2e-\d+-\d+$/.test(container)) {
    throw new Error("Playwright E2E container name is missing or invalid.");
  }

  return execFileSync("docker", ["exec", "-i", container, "php", "artisan", ...args], {
    encoding: "utf8",
    stdio,
  });
};

const ciArtisan: ArtisanRunner = (args, stdio) => {
  return execFileSync("php", ["artisan", ...args], {
    encoding: "utf8",
    stdio,
  });
};

function resolvedTarget(artisan: ArtisanRunner): DatabaseTarget {
  const output = artisan(
    [
      "tinker",
      "--execute",
      "$c = DB::connection();" +
        'echo \'TARGET:\'.json_encode(["host" => $c->getConfig("host"),' +
        ' "database" => $c->getConfig("database")]);',
    ],
    "pipe",
  );
  const probe = /TARGET:(\{.*?\})/.exec(output)?.[1];

  if (probe === undefined) {
    throw new Error(`E2E の接続先を確認できませんでした:\n${output}`);
  }

  return JSON.parse(probe) as DatabaseTarget;
}

async function waitForDatabase(artisan: ArtisanRunner, timeoutMs = 60_000): Promise<void> {
  const startedAt = Date.now();

  for (;;) {
    try {
      artisan(["tinker", "--execute", "DB::connection()->getPdo();"], "pipe");

      return;
    } catch (error) {
      if (Date.now() - startedAt > timeoutMs) {
        throw new Error(
          `E2E データベースへ接続できません。接続先と起動状態を確認してください。\n${String(error)}`,
        );
      }

      await sleep(500);
    }
  }
}

export async function prepareE2eDatabase(
  artisan: ArtisanRunner,
  expectedTarget: DatabaseTarget,
): Promise<void> {
  assertE2eTarget(resolvedTarget(artisan), expectedTarget);
  await waitForDatabase(artisan);
  artisan(["migrate:fresh", "--force", "--seed"], "inherit");
}

export default async function globalSetup(): Promise<void> {
  const isCI = Boolean(process.env.CI);

  await prepareE2eDatabase(isCI ? ciArtisan : dockerArtisan, isCI ? CI_E2E_TARGET : E2E_TARGET);
}

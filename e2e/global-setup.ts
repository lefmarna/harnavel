import { execFileSync } from "node:child_process";
import path from "node:path";
import { setTimeout as sleep } from "node:timers/promises";

import {
  assertE2eTarget,
  CI_E2E_TARGET,
  E2E_TARGET,
  isDatabaseTarget,
} from "./e2e-database-target";
import type { DatabaseTarget } from "./e2e-database-target";
import { assertViteDevAssets, createViteDevAssetsProbe } from "./vite-dev-assets";

// Playwright は globalSetup を config 基準で解決するだけで chdir しないため、起動ディレクトリに依存させない。
const REPO_ROOT = path.resolve(import.meta.dirname, "..");

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

  const target: unknown = JSON.parse(probe);

  if (!isDatabaseTarget(target)) {
    throw new Error(`E2E の接続先の形式が想定と異なります:\n${probe}`);
  }

  return target;
}

async function waitForDatabase(artisan: ArtisanRunner, timeoutMs = 60_000): Promise<void> {
  const startedAt = Date.now();

  for (;;) {
    try {
      artisan(["tinker", "--execute", "DB::connection()->getPdo();"], "pipe");

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

export async function prepareE2eDatabase(
  artisan: ArtisanRunner,
  expectedTarget: DatabaseTarget,
): Promise<void> {
  assertE2eTarget(resolvedTarget(artisan), expectedTarget);
  await waitForDatabase(artisan);
  artisan(["migrate:fresh", "--force", "--seed"], "inherit");
}

// CI は dev server を持たず、ビルド済みアセットが常にそのコミットの内容になる。
export function usesViteDevServer(env: { CI?: string }): boolean {
  return !env.CI;
}

export default async function globalSetup(): Promise<void> {
  const isCI = Boolean(process.env.CI);

  if (usesViteDevServer(process.env)) {
    await assertViteDevAssets(createViteDevAssetsProbe(REPO_ROOT));
  }

  await prepareE2eDatabase(isCI ? ciArtisan : dockerArtisan, isCI ? CI_E2E_TARGET : E2E_TARGET);
}

import assert from "node:assert/strict";
import { describe, it } from "node:test";

import { CI_E2E_TARGET } from "./e2e-database-target";
import { prepareE2eDatabase, usesViteDevServer } from "./global-setup";
import type { ArtisanRunner } from "./global-setup";

describe("E2Eデータベースの初期化", () => {
  it("CIでもPlaywrightプロセスごとに初期状態へ戻す", async () => {
    const calls: string[][] = [];
    const artisan: ArtisanRunner = (args) => {
      calls.push(args);

      return args.some((argument) => argument.includes("TARGET:"))
        ? `TARGET:${JSON.stringify(CI_E2E_TARGET)}`
        : "";
    };

    await prepareE2eDatabase(artisan, CI_E2E_TARGET);
    await prepareE2eDatabase(artisan, CI_E2E_TARGET);

    assert.equal(calls.filter(([command]) => command === "migrate:fresh").length, 2);
  });
});

describe("Vite開発アセットのガードを適用する範囲", () => {
  it("ローカルだけで適用しCIでは適用しない", () => {
    assert.equal(usesViteDevServer({}), true);
    assert.equal(usesViteDevServer({ CI: "true" }), false);
  });
});

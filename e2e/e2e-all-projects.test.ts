import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { describe, it } from "node:test";
import { fileURLToPath } from "node:url";

type PackageJson = {
  scripts?: Record<string, string>;
};

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const packageJson = JSON.parse(
  fs.readFileSync(path.join(ROOT, "package.json"), "utf8"),
) as PackageJson;

describe("全ブラウザE2Eコマンド", () => {
  it("ブラウザごとにPlaywrightを別プロセスで順番に実行する", () => {
    assert.equal(
      packageJson.scripts?.["test:e2e:all"],
      "pnpm test:e2e:chromium && pnpm test:e2e:firefox && pnpm test:e2e:webkit",
    );
    assert.equal(packageJson.scripts?.["test:e2e:chromium"], "playwright test --project=chromium");
    assert.equal(packageJson.scripts?.["test:e2e:firefox"], "playwright test --project=firefox");
    assert.equal(packageJson.scripts?.["test:e2e:webkit"], "playwright test --project=webkit");
  });
});

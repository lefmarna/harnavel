import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

test("test:unit は各 frontend project の mts・cts テストを実行する", () => {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "frontend-unit-discovery-"));
  const packageJson = JSON.parse(readFileSync(path.join(ROOT, "package.json"), "utf8")) as {
    scripts: { "test:unit": string };
  };
  const cases = [
    ["resources/js", "ts"],
    ["resources/js", "mts"],
    ["resources/js", "cts"],
    ["e2e", "ts"],
    ["e2e", "mts"],
    ["e2e", "cts"],
    ["scripts", "ts"],
    ["scripts", "mts"],
    ["scripts", "cts"],
  ] as const;

  writeFileSync(
    path.join(fixture, "package.json"),
    JSON.stringify({
      private: true,
      type: "module",
      scripts: { "test:unit": packageJson.scripts["test:unit"] },
    }),
  );
  writeFileSync(path.join(fixture, "pnpm-workspace.yaml"), "verifyDepsBeforeRun: false\n");
  symlinkSync(path.join(ROOT, "node_modules"), path.join(fixture, "node_modules"), "dir");

  for (const [directory, extension] of cases) {
    const name = `discovers ${directory} ${extension}`;
    const targetDirectory = path.join(fixture, directory);

    mkdirSync(targetDirectory, { recursive: true });
    writeFileSync(
      path.join(targetDirectory, `discovery.test.${extension}`),
      `import { test } from "node:test";\ntest(${JSON.stringify(name)}, () => {});\n`,
    );
  }

  try {
    const result = spawnSync("pnpm", ["run", "test:unit"], {
      cwd: fixture,
      encoding: "utf8",
      env: {
        ...process.env,
        NODE_TEST_CONTEXT: undefined,
      },
    });

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);

    for (const [directory, extension] of cases) {
      assert.match(result.stdout, new RegExp(`discovers ${directory} ${extension}`));
    }
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
});

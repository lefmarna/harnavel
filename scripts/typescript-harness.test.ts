import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdtempSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

import { resolveConfig } from "vite";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const TSC = path.join(ROOT, "node_modules/.bin/tsc");
const PROJECTS = [
  "tsconfig.json",
  "resources/js/tsconfig.json",
  "scripts/tsconfig.json",
  "e2e/tsconfig.json",
] as const;

type CompileFixtureOptions = {
  includeProjectFiles?: boolean;
};

test("browser program は Node global を拒否し、unit test program は Node API を許可する", () => {
  assertDiagnosticsInclude(
    "tsconfig.json",
    "resources/js/__typescript-boundary-fixture.ts",
    "console.log(process.env.NODE_ENV);\n",
    2591,
  );
  assertNoDiagnostics(
    "resources/js/tsconfig.json",
    "resources/js/__typescript-test-fixture.test.ts",
    'import test from "node:test";\ntest("fixture", () => undefined);\n',
  );
});

test("browser program はbrowser floorで利用できるES2023 Array APIを許可する", async () => {
  const viteConfig = await resolveConfig(
    { configFile: path.join(ROOT, "vite.config.ts") },
    "build",
  );

  assert.deepEqual(viteConfig.build.target, [
    "chrome111",
    "edge111",
    "firefox115",
    "safari16.4",
    "ios16.4",
  ]);
  assertNoDiagnostics(
    "tsconfig.json",
    "resources/js/__typescript-runtime-api-fixture.ts",
    "const values = [3, 1, 2];\nconsole.log(values.toSorted());\n",
  );
});

test("browser program はbrowser floorで利用できないES2023 APIを拒否する", () => {
  assertDiagnosticsInclude(
    "tsconfig.json",
    "resources/js/__typescript-collection-api-fixture.ts",
    'const key = Symbol("key");\nnew WeakMap<symbol, string>().set(key, "value");\n',
    2344,
  );
  assertDiagnosticsInclude(
    "tsconfig.json",
    "resources/js/__typescript-intl-api-fixture.ts",
    'new Intl.NumberFormat("en", { roundingMode: "ceil" });\n',
    2769,
  );
});

test("frontend unit program はNode runtimeのES2023 APIを許可する", () => {
  assertNoDiagnostics(
    "resources/js/tsconfig.json",
    "resources/js/__typescript-node-runtime-api-fixture.test.ts",
    [
      'const key = Symbol("key");',
      'new WeakMap<symbol, string>().set(key, "value");',
      'new Intl.NumberFormat("en", { roundingMode: "ceil" });',
      "",
    ].join("\n"),
  );
});

test("unit test program はbrowser sourceと同じ共有型宣言を読み込む", () => {
  assertNoDiagnostics(
    "resources/js/tsconfig.json",
    "resources/js/__typescript-shared-types-fixture.test.ts",
    [
      'import { usePage } from "@inertiajs/react";',
      "export function hasPassword(): boolean {",
      "  return usePage().props.auth.hasPassword;",
      "}",
      "",
    ].join("\n"),
    { includeProjectFiles: true },
  );
});

test("Node program は browser global を拒否する", () => {
  assertDiagnosticsInclude(
    "scripts/tsconfig.json",
    "scripts/__typescript-boundary-fixture.ts",
    "console.log(document.title);\n",
    2584,
  );
});

test("Playwright program は browser callback 内の DOM API を許可する", () => {
  assertNoDiagnostics(
    "e2e/tsconfig.json",
    "e2e/__typescript-playwright-fixture.ts",
    [
      'import type { Page } from "@playwright/test";',
      "export async function readTitle(page: Page): Promise<string> {",
      "  return page.evaluate(() => document.title);",
      "}",
      "",
    ].join("\n"),
  );
});

test("全TypeScript program は共通compiler guardrailを維持する", () => {
  const source = [
    'import { ComponentProps } from "react";',
    'import "./missing-side-effect.js";',
    'export type ButtonProps = ComponentProps<"button">;',
    "export enum State { Ready }",
    "const values: string[] = [];",
    "values[0].toUpperCase();",
    "class Parent { save(): void {} }",
    "class Child extends Parent { save(): void {} }",
    "export function label(ready: boolean) { if (ready) return 'ready'; }",
    "console.log(Child);",
    "",
  ].join("\n");
  const expectedDiagnostics = [
    { code: 1484, guardrail: "verbatimModuleSyntax" },
    { code: 2882, guardrail: "noUncheckedSideEffectImports" },
    { code: 1294, guardrail: "erasableSyntaxOnly" },
    { code: 2532, guardrail: "noUncheckedIndexedAccess" },
    { code: 4114, guardrail: "noImplicitOverride" },
    { code: 7030, guardrail: "noImplicitReturns" },
  ];

  for (const config of PROJECTS) {
    const result = compileFixture(config, "__typescript-common-guardrails-fixture.ts", source);

    for (const { code, guardrail } of expectedDiagnostics) {
      assert.ok(
        result.diagnosticCodes.includes(code),
        `${config} does not enforce ${guardrail} (TS${code}).\n${result.output}`,
      );
    }
  }
});

test("first-party実装は一意のprogramに所属し、共有宣言も検査対象になる", () => {
  const owners = new Map<string, string[]>();

  for (const config of PROJECTS) {
    const projectRoot = path.dirname(path.join(ROOT, config));

    for (const file of showConfig(config).files ?? []) {
      const relative = normalizeRelative(path.resolve(projectRoot, file));
      const projectOwners = owners.get(relative) ?? [];

      projectOwners.push(config);
      owners.set(relative, projectOwners);
    }
  }

  const git = spawnSync("git", ["ls-files", "--cached", "--others", "--exclude-standard"], {
    cwd: ROOT,
    encoding: "utf8",
  });

  assert.equal(git.status, 0, git.stderr);

  const firstPartyFiles = git.stdout.split("\n").filter(isFirstPartyTypeScript).sort();
  const invalidOwnership = firstPartyFiles
    .map((file) => ({ file, projects: owners.get(file) ?? [] }))
    .filter(
      ({ file, projects }) =>
        projects.length === 0 || (!isTypeScriptDeclaration(file) && projects.length !== 1),
    );

  assert.deepEqual(invalidOwnership, []);
});

function showConfig(relativeConfig: string): { files?: string[] } {
  const result = spawnSync(TSC, ["-p", path.join(ROOT, relativeConfig), "--showConfig"], {
    cwd: ROOT,
    encoding: "utf8",
  });

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);

  return JSON.parse(result.stdout) as { files?: string[] };
}

function compileFixture(
  relativeConfig: string,
  relativeFixture: string,
  source: string,
  options: CompileFixtureOptions = {},
): { diagnosticCodes: number[]; output: string; status: number | null } {
  const fixtureDirectory = mkdtempSync(path.join(os.tmpdir(), "typescript-harness-"));
  const fixture = path.join(fixtureDirectory, path.basename(relativeFixture));
  const config = path.join(fixtureDirectory, "tsconfig.json");

  symlinkSync(
    path.join(ROOT, "node_modules"),
    path.join(fixtureDirectory, "node_modules"),
    "junction",
  );
  writeFileSync(fixture, source);
  const projectRoot = path.dirname(path.join(ROOT, relativeConfig));
  const projectFiles = options.includeProjectFiles
    ? (showConfig(relativeConfig).files ?? []).map((file) => path.resolve(projectRoot, file))
    : [];

  writeFileSync(
    config,
    JSON.stringify({
      extends: path.join(ROOT, relativeConfig),
      files: [path.basename(fixture), ...projectFiles],
      include: [],
      exclude: [],
    }),
  );

  try {
    const result = spawnSync(TSC, ["-p", config, "--pretty", "false"], {
      cwd: fixtureDirectory,
      encoding: "utf8",
    });
    const output = `${result.stdout}\n${result.stderr}`.trim();

    return {
      diagnosticCodes: [...output.matchAll(/TS(\d+):/g)].map((match) => Number(match[1])),
      output,
      status: result.status,
    };
  } finally {
    rmSync(fixtureDirectory, { force: true, recursive: true });
  }
}

function assertDiagnosticsInclude(
  config: string,
  fixture: string,
  source: string,
  diagnosticCode: number,
): void {
  const result = compileFixture(config, fixture, source);

  assert.ok(
    result.diagnosticCodes.includes(diagnosticCode),
    result.output || `Expected TS${diagnosticCode}, but tsc exited with ${result.status}.`,
  );
}

function assertNoDiagnostics(
  config: string,
  fixture: string,
  source: string,
  options?: CompileFixtureOptions,
): void {
  const result = compileFixture(config, fixture, source, options);

  assert.equal(result.status, 0, result.output);
}

function normalizeRelative(file: string): string {
  return path.relative(ROOT, file).split(path.sep).join("/");
}

function isFirstPartyTypeScript(file: string): boolean {
  return (
    /^(?:resources\/js|scripts|e2e)\/.*\.(?:cts|mts|ts|tsx)$/.test(file) ||
    file === "vite.config.ts" ||
    file === "playwright.config.ts"
  );
}

function isTypeScriptDeclaration(file: string): boolean {
  return /\.d\.(?:cts|mts|ts)$/.test(file);
}

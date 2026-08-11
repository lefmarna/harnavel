import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const OXLINT = path.join(ROOT, "node_modules/.bin/oxlint");
const OXFMT = path.join(ROOT, "node_modules/.bin/oxfmt");
const TSC = path.join(ROOT, "node_modules/.bin/tsc");

test("外部入力からの any 代入を type-aware lint が拒否する", () => {
  assertOxlintRejects(
    'const value: string = JSON.parse("{}")\nconsole.log(value)\n',
    "no-unsafe-assignment",
  );
});

test("未処理の Promise を type-aware lint が拒否する", () => {
  assertOxlintRejects("async function save(): Promise<void> {}\nsave()\n", "no-floating-promises");
});

test("union の未処理分岐を type-aware lint が拒否する", () => {
  assertOxlintRejects(
    'type State = "ready" | "failed"\nfunction label(state: State) {\n  switch (state) {\n    case "ready": return "Ready"\n  }\n}\nconsole.log(label("ready"))\n',
    "switch-exhaustiveness-check",
  );
});

test("default 分岐があっても union の未処理分岐を type-aware lint が拒否する", () => {
  assertOxlintRejects(
    'type State = "ready" | "failed"\nfunction label(state: State) {\n  switch (state) {\n    case "ready": return "Ready"\n    default: return "Unknown"\n  }\n}\nconsole.log(label("ready"))\n',
    "switch-exhaustiveness-check",
  );
});

test("TypeScript の型エラーを無視する directive を Oxlint が拒否する", () => {
  assertOxlintRejects(
    "// @ts-ignore: bypass the type error\nconst value: string = 1\nconsole.log(value)\n",
    "ban-ts-comment",
  );
});

test("TypeScript のファイル全体の型検査を無効にする directive を Oxlint が拒否する", () => {
  assertOxlintRejects(
    "// @ts-nocheck: bypass all type errors\nconst value: string = 1\nconsole.log(value)\n",
    "ban-ts-comment",
  );
});

test("空オブジェクトと誤解されやすい TypeScript の {} 型を Oxlint が拒否する", () => {
  assertOxlintRejects(
    "function accept(value: {}): void { console.log(value) }\naccept(42)\n",
    "no-empty-object-type",
  );
});

test("呼び出し契約を失う TypeScript の Function 型を Oxlint が拒否する", () => {
  assertOxlintRejects(
    "function register(callback: Function): void { console.log(callback) }\nregister(() => undefined)\n",
    "no-unsafe-function-type",
  );
});

test("理由を記した局所的な TypeScript error expectation は許可する", () => {
  assertOxlintAccepts(
    "// @ts-expect-error: verify the intentionally invalid fixture\nconst value: string = 1\nconsole.log(value)\n",
  );
});

test("JavaScript の correctness 違反を Oxlint が拒否する", () => {
  assertOxlintRejectsJavaScript("const value = 1;\nvalue = 2;\ndebugger;\n", "no-const-assign");
});

test("生成されたフロントエンドファイルを Oxfmt が変更しない", () => {
  const target = path.join(resourcesGeneratedDirectory(), `__quality-ignore-${process.pid}.ts`);
  const source = "export const generated={value:1}\n";

  writeFileSync(target, source);

  try {
    const result = spawnSync(OXFMT, ["--no-error-on-unmatched-pattern", target], {
      cwd: ROOT,
      encoding: "utf8",
    });

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.equal(readFileSync(target, "utf8"), source);
  } finally {
    rmSync(target, { force: true });
  }
});

test("Oxfmt が import と Tailwind class の順序を正規化する", () => {
  const source = 'import z from "./z"\nimport a from "./a"\nconst value = cn("p-4 flex")\n';
  const virtualPath = path.join(ROOT, "resources/js/__quality-sort-fixture.tsx");

  const typeCheck = spawnSync(TSC, ["--pretty", "false", "--noEmit"], {
    cwd: ROOT,
    encoding: "utf8",
  });

  assert.equal(
    typeCheck.status,
    0,
    `formatter fixture must not enter the application TypeScript program\n${typeCheck.stdout}\n${typeCheck.stderr}`,
  );

  const result = spawnSync(OXFMT, ["--stdin-filepath", virtualPath], {
    cwd: ROOT,
    encoding: "utf8",
    input: source,
  });

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  assert.equal(
    result.stdout,
    'import a from "./a";\nimport z from "./z";\nconst value = cn("flex p-4");\n',
  );
});

function resourcesGeneratedDirectory(): string {
  const directory = path.join(ROOT, "resources/js/routes");

  mkdirSync(directory, { recursive: true });

  return directory;
}

function createTypeAwareFixture(source: string): string {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "frontend-quality-"));

  mkdirSync(path.join(fixture, "src"));
  writeFileSync(
    path.join(fixture, "tsconfig.json"),
    JSON.stringify({
      compilerOptions: {
        lib: ["ES2022", "DOM"],
        module: "ESNext",
        moduleResolution: "bundler",
        noEmit: true,
        strict: true,
        target: "ES2022",
      },
      include: ["src/**/*.ts"],
    }),
  );
  writeFileSync(path.join(fixture, "src/example.ts"), source);

  return fixture;
}

function assertOxlintRejects(source: string, rule: string): void {
  const fixture = createTypeAwareFixture(source);

  try {
    const result = runOxlint(fixture);

    assert.notEqual(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.match(`${result.stdout}\n${result.stderr}`, new RegExp(rule));
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
}

function assertOxlintAccepts(source: string): void {
  const fixture = createTypeAwareFixture(source);

  try {
    const result = runOxlint(fixture);

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
}

function assertOxlintRejectsJavaScript(source: string, rule: string): void {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "frontend-quality-js-"));
  const target = path.join(fixture, "example.js");

  writeFileSync(target, source);

  try {
    const result = spawnSync(OXLINT, ["-c", path.join(ROOT, ".oxlintrc.json"), target], {
      encoding: "utf8",
    });

    assert.notEqual(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.match(`${result.stdout}\n${result.stderr}`, new RegExp(rule));
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
}

function runOxlint(fixture: string) {
  return spawnSync(
    OXLINT,
    ["-c", path.join(ROOT, ".oxlintrc.json"), "--type-aware", path.join(fixture, "src/example.ts")],
    { encoding: "utf8" },
  );
}

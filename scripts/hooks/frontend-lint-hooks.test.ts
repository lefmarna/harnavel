import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import os from "node:os";
import path from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const LEFTHOOK = path.join(ROOT, "node_modules/.bin/lefthook");
const STOP_HOOK = path.join(ROOT, "scripts/hooks/stop-verify.mjs");
const LOGGING_PNPM = '#!/bin/sh\nprintf "%s\\n" "$*" >> "$PNPM_LOG"\n';
const FAILING_FIX_PNPM = [
  "#!/bin/sh",
  'printf "%s\\n" "$*" >> "$PNPM_LOG"',
  'case "$*" in',
  '  *"exec oxlint --fix"*) echo "safe fix failed" >&2; exit 7 ;;',
  "  *) exit 0 ;;",
  "esac",
  "",
].join("\n");
const IGNORED_FILE_PNPM = [
  "#!/bin/sh",
  'printf "%s\\n" "$*" >> "$PNPM_LOG"',
  'case "$*" in',
  '  *"exec oxlint"*)',
  '    case " $* " in',
  '      *" --no-error-on-unmatched-pattern "*) exit 0 ;;',
  '      *) echo "No files found to lint." >&2; exit 1 ;;',
  "    esac",
  "    ;;",
  "  *) exit 0 ;;",
  "esac",
  "",
].join("\n");

type StopHookFixture = {
  fixture: string;
  pnpmLog: string;
  repo: string;
};

test("Oxlint が無視する生成ファイルだけでも pre-commit を通過する", () => {
  const result = spawnSync(
    "pnpm",
    [
      "exec",
      "lefthook",
      "run",
      "pre-commit",
      "--job",
      "fix staged frontend files with oxlint",
      "--file",
      "resources/js/routes/index.ts",
      "--force",
      "--no-stage-fixed",
      "--no-tty",
    ],
    {
      cwd: ROOT,
      encoding: "utf8",
    },
  );

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
});

test("pre-commit は明示的な JS・TS module 拡張子を lint と format の対象にする", () => {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "lefthook-frontend-"));
  const bin = path.join(fixture, "bin");
  const pnpmLog = path.join(fixture, "pnpm.log");
  const lintFiles = [
    "scripts/hooks/stop-verify.mjs",
    "scripts/hooks/__extension-fixture.cjs",
    "scripts/hooks/__extension-fixture.mts",
    "scripts/hooks/__extension-fixture.cts",
  ];
  const formatFiles = [...lintFiles, "mise.toml"];

  mkdirSync(bin);
  writeFileSync(path.join(bin, "pnpm"), LOGGING_PNPM, { mode: 0o755 });

  try {
    for (const file of lintFiles) {
      const result = runLefthookJob(
        "fix staged frontend files with oxlint",
        file,
        fixture,
        pnpmLog,
      );

      assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    }

    for (const file of formatFiles) {
      const result = runLefthookJob("format staged frontend files", file, fixture, pnpmLog);

      assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    }

    const commands = readFileSync(pnpmLog, "utf8");

    for (const file of lintFiles) {
      assert.match(commands, new RegExp(`^exec oxlint .*${escapeRegExp(file)}$`, "m"));
    }

    for (const file of formatFiles) {
      assert.match(commands, new RegExp(`^exec oxfmt .*${escapeRegExp(file)}$`, "m"));
    }
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
});

test(".oxlintrc.json の変更時は stop hook がリポジトリ全体を lint する", () => {
  const fixture = createStopHookFixture(".oxlintrc.json", "{}\n", '{"rules": {}}\n', LOGGING_PNPM);

  try {
    const result = runStopHook(fixture);

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.match(readFileSync(fixture.pnpmLog, "utf8"), /^lint:check$/m);
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("Oxlint が無視する生成ファイルだけでも stop hook を通過する", () => {
  const fixture = createStopHookFixture(
    "resources/js/routes/index.ts",
    "export const value = 1\n",
    "export const value = 2\n",
    IGNORED_FILE_PNPM,
  );

  try {
    const result = runStopHook(fixture);
    const pnpmLog = readFileSync(fixture.pnpmLog, "utf8");

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.equal(result.stdout.trim(), "");
    assert.match(
      pnpmLog,
      /^exec oxlint --fix --no-error-on-unmatched-pattern resources\/js\/routes\/index\.ts$/m,
    );
    assert.match(pnpmLog, /^lint:check$/m);
    assert.doesNotMatch(pnpmLog, /eslint/);
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("stop hook は safe autofix 後に format・lint・unit test を検証する", () => {
  const fixture = createStopHookFixture(
    "resources/js/example.ts",
    "export const value = 1\n",
    "export const value = 2\n",
    LOGGING_PNPM,
  );

  try {
    const result = runStopHook(fixture);
    const commands = readFileSync(fixture.pnpmLog, "utf8").trim().split("\n");
    const lintFix = commands.findIndex((command) => command.startsWith("exec oxlint --fix "));
    const formatFix = commands.findIndex((command) => command.startsWith("exec oxfmt "));
    const lintCheck = commands.indexOf("lint:check");
    const formatCheck = commands.findIndex((command) => command.startsWith("exec oxfmt --check "));

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.ok(lintFix >= 0, commands.join("\n"));
    assert.ok(formatFix > lintFix, commands.join("\n"));
    assert.ok(lintCheck > formatFix, commands.join("\n"));
    assert.ok(formatCheck > formatFix, commands.join("\n"));
    assert.ok(commands.includes("test:unit"), commands.join("\n"));
    assert.ok(commands.includes("types:check"), commands.join("\n"));
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("stop hook は safe autofix の失敗を block する", () => {
  const fixture = createStopHookFixture(
    "resources/js/example.ts",
    "export const value = 1\n",
    "export const value = 2\n",
    FAILING_FIX_PNPM,
  );

  try {
    const result = runStopHook(fixture);
    const response = JSON.parse(result.stdout) as {
      decision: string;
      reason: string;
    };

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.equal(response.decision, "block");
    assert.match(response.reason, /Oxlint safe fix/);
    assert.match(response.reason, /safe fix failed/);
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("stop hook は Markdown を format してから検証する", () => {
  const fixture = createStopHookFixture("README.md", "# Before\n", "# After\n", LOGGING_PNPM);

  try {
    const result = runStopHook(fixture);
    const commands = readFileSync(fixture.pnpmLog, "utf8");

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.match(commands, /^exec oxfmt --no-error-on-unmatched-pattern README\.md$/m);
    assert.match(commands, /^exec oxfmt --check --no-error-on-unmatched-pattern README\.md$/m);
    assert.match(commands, /^lint:docs$/m);
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("stop hook は mise.toml を format し frontend toolchain 全体を検証する", () => {
  const fixture = createStopHookFixture(
    "mise.toml",
    '[tools]\nnode = "24.18.0"\npnpm = "11.18.0"\n',
    '[tools]\nnode="24.18.1"\npnpm="11.18.0"\n',
    LOGGING_PNPM,
  );

  try {
    const result = runStopHook(fixture);

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    assert.ok(existsSync(fixture.pnpmLog), "mise.toml change did not run frontend tools");

    const commands = readFileSync(fixture.pnpmLog, "utf8");

    assert.match(commands, /^exec oxfmt --no-error-on-unmatched-pattern mise\.toml$/m);
    assert.match(commands, /^format:check$/m);
    assert.match(commands, /^lint:check$/m);
    assert.match(commands, /^types:check$/m);
    assert.match(commands, /^test:unit$/m);
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true });
  }
});

test("stop hook は mts・cts を safe fix してから検証する", () => {
  const fixtures = ["mts", "cts"].map((extension) =>
    createStopHookFixture(
      `scripts/__extension-fixture.${extension}`,
      "export const value = 1\n",
      "export const value = 2\n",
      LOGGING_PNPM,
    ),
  );

  try {
    for (const fixture of fixtures) {
      const result = runStopHook(fixture);

      assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
      assert.ok(existsSync(fixture.pnpmLog), "module extension change did not run frontend tools");

      const commands = readFileSync(fixture.pnpmLog, "utf8");

      assert.match(commands, /^exec oxlint --fix --no-error-on-unmatched-pattern /m);
      assert.match(commands, /^exec oxfmt --no-error-on-unmatched-pattern /m);
      assert.match(commands, /^lint:check$/m);
      assert.match(commands, /^types:check$/m);
      assert.match(commands, /^test:unit$/m);
    }
  } finally {
    for (const fixture of fixtures) {
      rmSync(fixture.fixture, { force: true, recursive: true });
    }
  }
});

test("Markdown lint は IPC socket を禁止した環境でも実行できる", () => {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "lint-docs-runner-"));
  const blockIpc = path.join(fixture, "block-ipc.mjs");

  writeFileSync(
    blockIpc,
    'import net from "node:net";\nnet.Server.prototype.listen = function () { throw new Error("IPC disabled for test"); };\n',
  );

  try {
    const result = spawnSync("pnpm", ["lint:docs"], {
      cwd: ROOT,
      encoding: "utf8",
      env: {
        ...process.env,
        NODE_OPTIONS: `${process.env.NODE_OPTIONS ?? ""} --import=${blockIpc}`.trim(),
      },
    });

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  } finally {
    rmSync(fixture, { force: true, recursive: true });
  }
});

test("formatter・TypeScript 設定変更時は対応する全体検証を行う", () => {
  const formatter = createStopHookFixture(
    ".oxfmtrc.json",
    "{}\n",
    '{"sortImports": true}\n',
    LOGGING_PNPM,
  );
  const typescriptConfigs = ["tsconfig.json", "scripts/tsconfig.json", "e2e/tsconfig.json"].map(
    (file) => createStopHookFixture(file, "{}\n", '{"compilerOptions": {}}\n', LOGGING_PNPM),
  );

  try {
    assert.equal(runStopHook(formatter).status, 0);
    assert.match(readFileSync(formatter.pnpmLog, "utf8"), /^format:check$/m);

    for (const typescript of typescriptConfigs) {
      assert.equal(runStopHook(typescript).status, 0);
      const typescriptCommands = readFileSync(typescript.pnpmLog, "utf8");

      assert.match(typescriptCommands, /^types:check$/m);
      assert.match(typescriptCommands, /^lint:check$/m);
    }
  } finally {
    rmSync(formatter.fixture, { force: true, recursive: true });

    for (const typescript of typescriptConfigs) {
      rmSync(typescript.fixture, { force: true, recursive: true });
    }
  }
});

function createStopHookFixture(
  relativeFile: string,
  baseline: string,
  changed: string,
  pnpmScript: string,
): StopHookFixture {
  const fixture = mkdtempSync(path.join(os.tmpdir(), "stop-verify-"));
  const repo = path.join(fixture, "repo");
  const bin = path.join(fixture, "bin");
  const pnpmLog = path.join(fixture, "pnpm.log");
  const target = path.join(repo, relativeFile);

  mkdirSync(path.dirname(target), { recursive: true });
  mkdirSync(bin);
  writeFileSync(target, baseline);
  runGit(repo, ["init", "--quiet"]);
  runGit(repo, ["add", relativeFile]);
  runGit(repo, [
    "-c",
    "user.name=Test",
    "-c",
    "user.email=test@example.com",
    "-c",
    "commit.gpgsign=false",
    "commit",
    "--quiet",
    "-m",
    "baseline",
  ]);
  writeFileSync(target, changed);
  writeFileSync(path.join(bin, "pnpm"), pnpmScript, { mode: 0o755 });

  return { fixture, pnpmLog, repo };
}

function runStopHook(fixture: StopHookFixture) {
  return spawnSync(process.execPath, [STOP_HOOK], {
    cwd: fixture.repo,
    encoding: "utf8",
    env: {
      ...process.env,
      PATH: `${path.join(fixture.fixture, "bin")}${path.delimiter}${process.env.PATH ?? ""}`,
      PNPM_LOG: fixture.pnpmLog,
    },
    input: '{"stop_hook_active":false}',
  });
}

function runLefthookJob(job: string, file: string, fixture: string, pnpmLog: string) {
  return spawnSync(
    LEFTHOOK,
    ["run", "pre-commit", "--job", job, "--file", file, "--force", "--no-stage-fixed", "--no-tty"],
    {
      cwd: ROOT,
      encoding: "utf8",
      env: {
        ...process.env,
        PATH: `${path.join(fixture, "bin")}${path.delimiter}${process.env.PATH ?? ""}`,
        PNPM_LOG: pnpmLog,
      },
    },
  );
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function runGit(cwd: string, args: string[]): void {
  const result = spawnSync("git", args, { cwd, encoding: "utf8" });

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
}

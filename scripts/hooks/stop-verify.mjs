#!/usr/bin/env node
import { spawn, spawnSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { delimiter, extname, join } from "node:path";

const PHPSTAN_EXCLUDED_DIRS = ["tests/"];
const isExcludedFromPhpStan = (file) => PHPSTAN_EXCLUDED_DIRS.some((dir) => file.startsWith(dir));

const OXFMT_EXTS = new Set([
  ".cjs",
  ".css",
  ".cts",
  ".html",
  ".js",
  ".jsx",
  ".json",
  ".md",
  ".mjs",
  ".mts",
  ".toml",
  ".ts",
  ".tsx",
  ".yaml",
  ".yml",
]);
const LINT_EXTS = new Set([".cjs", ".cts", ".js", ".jsx", ".mjs", ".mts", ".ts", ".tsx"]);
const LINT_CONFIG_FILES = new Set(["vite.config.ts"]);
const FORMAT_CONFIG_FILES = new Set(["vite.config.ts"]);
const FRONTEND_TOOLCHAIN_FILES = new Set([
  "mise.toml",
  "package.json",
  "pnpm-lock.yaml",
  "pnpm-workspace.yaml",
]);
const PHP_EXTS = new Set([".php"]);
const TS_EXTS = new Set([".cts", ".mts", ".ts", ".tsx"]);
const MD_EXTS = new Set([".md"]);

const payload = JSON.parse(readFileSync(0, "utf8"));

if (payload.stop_hook_active) {
  process.exit(0);
}

const repo = run(["git", "rev-parse", "--show-toplevel"]).stdout.trim();
const { all, existing } = changedFiles(repo);

if (all.length === 0) {
  process.exit(0);
}

const isBlade = (file) => file.endsWith(".blade.php");
const isTypeConfig = (file) => /(^|\/)tsconfig(?:\.[^/]+)?\.json$/.test(file);

const oxfmtAll = byExt(all, OXFMT_EXTS);
const oxfmtFiles = byExt(existing, OXFMT_EXTS);
const lintAll = byExt(all, LINT_EXTS);
const lintFiles = byExt(existing, LINT_EXTS);
const phpAll = byExt(all, PHP_EXTS).filter((file) => !isBlade(file));
const phpExisting = byExt(existing, PHP_EXTS).filter((file) => !isBlade(file));
const phpStanAll = phpAll.filter((file) => !isExcludedFromPhpStan(file));
const phpStanExisting = phpExisting.filter((file) => !isExcludedFromPhpStan(file));
const hasTs = byExt(all, TS_EXTS).length > 0;
const hasMd = byExt(all, MD_EXTS).length > 0;
const hasJsTsDeletions = lintAll.length > lintFiles.length;
const hasOxfmtDeletions = oxfmtAll.length > oxfmtFiles.length;
const hasLintConfigChange = all.some((file) => LINT_CONFIG_FILES.has(file));
const hasFormatConfigChange = all.some((file) => FORMAT_CONFIG_FILES.has(file));
const hasTypeConfigChange = all.some(isTypeConfig);
const hasFrontendToolchainChange = all.some((file) => FRONTEND_TOOLCHAIN_FILES.has(file));
const hasPhpStanDeletions = phpStanAll.length > phpStanExisting.length;
const hasAnyPhpChange = byExt(all, PHP_EXTS).length > 0;
const hasPnpm = hasCommand("pnpm");
const dockerReady = hasAnyPhpChange && hasCommand("docker") && dockerAppRunning(repo);

// 自動修正は lefthook と同じ順序で逐次実行する。

const fixFailures = [];

if (
  !hasPnpm &&
  (oxfmtAll.length ||
    lintAll.length ||
    hasLintConfigChange ||
    hasFormatConfigChange ||
    hasTypeConfigChange ||
    hasFrontendToolchainChange)
) {
  fixFailures.push({
    title: "Frontend tools",
    output: "pnpm is required to verify frontend changes.",
  });
}

if (dockerReady && phpExisting.length) {
  captureFailure(
    ["docker", "compose", "exec", "-T", "app", "vendor/bin/pint", "--format=agent", ...phpExisting],
    repo,
    "Pint format",
    fixFailures,
  );
}

if (lintFiles.length && hasPnpm) {
  captureFailure(
    ["pnpm", "exec", "vp", "lint", "--fix", "--no-error-on-unmatched-pattern", ...lintFiles],
    repo,
    "Oxlint safe fix",
    fixFailures,
  );
}

if (oxfmtFiles.length && hasPnpm) {
  captureFailure(
    ["pnpm", "exec", "vp", "fmt", "--no-error-on-unmatched-pattern", ...oxfmtFiles],
    repo,
    "Oxfmt format",
    fixFailures,
  );
}

// 検証は対象を絞り、並列実行する。

const tasks = [];

if (
  hasPnpm &&
  (lintAll.length || hasLintConfigChange || hasTypeConfigChange || hasFrontendToolchainChange)
) {
  if (
    hasTs ||
    hasJsTsDeletions ||
    hasLintConfigChange ||
    hasTypeConfigChange ||
    hasFrontendToolchainChange
  ) {
    tasks.push(runAsync(["pnpm", "lint:check"], repo, "Frontend lint"));
  } else {
    tasks.push(
      runAsync(
        [
          "pnpm",
          "exec",
          "vp",
          "lint",
          "--type-aware",
          "--no-error-on-unmatched-pattern",
          ...lintFiles,
        ],
        repo,
        "Oxlint",
      ),
    );
  }
}

if (hasPnpm && (hasOxfmtDeletions || hasFormatConfigChange || hasFrontendToolchainChange)) {
  tasks.push(runAsync(["pnpm", "format:check"], repo, "Frontend format"));
}

if (hasPnpm && (hasTs || hasTypeConfigChange || hasFrontendToolchainChange)) {
  tasks.push(runAsync(["pnpm", "types:check"], repo, "Types"));
}

if (hasPnpm && (lintAll.length || hasFrontendToolchainChange)) {
  tasks.push(runAsync(["pnpm", "test:unit"], repo, "Frontend unit tests"));
}

if (hasMd && hasPnpm) {
  tasks.push(runAsync(["pnpm", "lint:docs"], repo, "Markdown links"));
}

if (dockerReady) {
  if (phpStanAll.length) {
    tasks.push(
      hasPhpStanDeletions
        ? runAsync(
            ["docker", "compose", "exec", "-T", "app", "composer", "analyse"],
            repo,
            "Larastan",
          )
        : runAsync(
            [
              "docker",
              "compose",
              "exec",
              "-T",
              "app",
              "vendor/bin/phpstan",
              "analyse",
              "--memory-limit=1G",
              "--no-progress",
              ...phpStanExisting,
            ],
            repo,
            "Larastan",
          ),
    );
  }
}

const failures = [
  ...fixFailures,
  ...(await Promise.all(tasks)).filter((result) => result !== null),
];

if (failures.length) {
  const reason = [
    "Verification failed before completion. Fix these issues before completing the task.",
    ...failures.map((f) => `\n[${f.title}]\n${f.output}`),
  ].join("\n");

  console.log(
    JSON.stringify({
      decision: "block",
      reason,
    }),
  );
}

function changedFiles(cwd) {
  const tracked = run(["git", "diff", "--name-only", "-z", "HEAD"], cwd);
  const untracked = run(["git", "ls-files", "--others", "--exclude-standard", "-z"], cwd);
  const merged = [...tracked.stdout.split("\0"), ...untracked.stdout.split("\0")];
  const changed = [...new Set(merged)]
    .filter(Boolean)
    .toSorted((left, right) => left.localeCompare(right));
  const present = changed.filter((file) => existsSync(join(cwd, file)));

  return { all: changed, existing: present };
}

function byExt(files, extensions) {
  return files.filter((file) => extensions.has(extname(file)));
}

function dockerAppRunning(cwd) {
  const result = run(["docker", "compose", "ps", "--services", "--filter", "status=running"], cwd);

  return result.status === 0 && result.stdout.split("\n").includes("app");
}

function hasCommand(command) {
  return (process.env.PATH ?? "")
    .split(delimiter)
    .some((directory) => existsSync(join(directory, command)));
}

function run(command, cwd) {
  return spawnSync(command[0], command.slice(1), {
    cwd,
    encoding: "utf8",
  });
}

function captureFailure(command, cwd, title, failures) {
  const result = run(command, cwd);

  if (result.status === 0) {
    return;
  }

  const output = `${result.stdout}\n${result.stderr}`.trim();
  failures.push({
    title,
    output: trim(output || `Exited with ${result.status}.`),
  });
}

function runAsync(command, cwd, title) {
  return new Promise((resolve) => {
    const child = spawn(command[0], command.slice(1), { cwd });
    let stdout = "";
    let stderr = "";

    child.stdout.on("data", (chunk) => {
      stdout += chunk;
    });
    child.stderr.on("data", (chunk) => {
      stderr += chunk;
    });
    child.on("error", (error) => {
      resolve({ title, output: `Failed to spawn: ${error.message}` });
    });
    child.on("close", (status) => {
      if (status === 0) {
        resolve(null);

        return;
      }

      const combined = `${stdout}\n${stderr}`.trim() || `Exited with ${status}.`;
      resolve({ title, output: trim(combined) });
    });
  });
}

function trim(output) {
  const lines = output.split("\n");

  return lines.length <= 40
    ? output
    : [...lines.slice(0, 40), `... truncated ${lines.length - 40} lines ...`].join("\n");
}

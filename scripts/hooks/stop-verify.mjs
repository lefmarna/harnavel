#!/usr/bin/env node
import { spawn, spawnSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { delimiter, extname, join } from "node:path";

// Pest bindings make isolated Larastan analysis of tests unreliable; Pint and Pest still run.
const PHPSTAN_EXCLUDED_DIRS = ["tests/"];
const isExcludedFromPhpStan = (file) => PHPSTAN_EXCLUDED_DIRS.some((dir) => file.startsWith(dir));

const OXFMT_EXTS = new Set([
  ".cjs",
  ".css",
  ".html",
  ".js",
  ".jsx",
  ".json",
  ".mjs",
  ".ts",
  ".tsx",
  ".yaml",
  ".yml",
]);
const LINT_EXTS = new Set([".cjs", ".js", ".jsx", ".mjs", ".ts", ".tsx"]);
const LINT_CONFIG_FILES = new Set([".oxlintrc.json"]);
const FORMAT_CONFIG_FILES = new Set([".oxfmtrc.json"]);
const TYPE_CONFIG_FILES = new Set(["tsconfig.json", "scripts/tsconfig.json", "e2e/tsconfig.json"]);
const FRONTEND_TOOLCHAIN_FILES = new Set(["package.json", "pnpm-lock.yaml", "pnpm-workspace.yaml"]);
const PHP_EXTS = new Set([".php"]);
const TS_EXTS = new Set([".ts", ".tsx"]);
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
const hasTypeConfigChange = all.some((file) => TYPE_CONFIG_FILES.has(file));
const hasFrontendToolchainChange = all.some((file) => FRONTEND_TOOLCHAIN_FILES.has(file));
const hasPhpStanDeletions = phpStanAll.length > phpStanExisting.length;
const hasAnyPhpChange = byExt(all, PHP_EXTS).length > 0;
const hasPnpm = hasCommand("pnpm");
const dockerReady = hasAnyPhpChange && hasCommand("docker") && dockerAppRunning(repo);

if (dockerReady) {
  ensureDockerGitSafeDirectory(repo);
}

// ---- Auto-fix phase (sequential, lefthook order) ----

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
    ["pnpm", "exec", "oxlint", "--fix", "--no-error-on-unmatched-pattern", ...lintFiles],
    repo,
    "Oxlint safe fix",
    fixFailures,
  );
}

if (oxfmtFiles.length && hasPnpm) {
  captureFailure(
    ["pnpm", "exec", "oxfmt", "--no-error-on-unmatched-pattern", ...oxfmtFiles],
    repo,
    "Oxfmt format",
    fixFailures,
  );
}

// ---- Verify phase (parallel, scoped where possible) ----

const tasks = [];

if (hasPnpm && (lintAll.length || hasLintConfigChange || hasFrontendToolchainChange)) {
  if (hasTs || hasJsTsDeletions || hasLintConfigChange || hasFrontendToolchainChange) {
    tasks.push(runAsync(["pnpm", "lint:check"], repo, "Frontend lint"));
  } else {
    tasks.push(
      runAsync(
        ["pnpm", "exec", "oxlint", "--type-aware", "--no-error-on-unmatched-pattern", ...lintFiles],
        repo,
        "Oxlint",
      ),
    );
  }
}

if (hasPnpm && (oxfmtAll.length || hasFormatConfigChange || hasFrontendToolchainChange)) {
  if (hasOxfmtDeletions || hasFormatConfigChange || hasFrontendToolchainChange) {
    tasks.push(runAsync(["pnpm", "format:check"], repo, "Frontend format"));
  } else {
    tasks.push(
      runAsync(
        ["pnpm", "exec", "oxfmt", "--check", "--no-error-on-unmatched-pattern", ...oxfmtFiles],
        repo,
        "Oxfmt",
      ),
    );
  }
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

  tasks.push(
    runAsync(
      [
        "docker",
        "compose",
        "exec",
        "-T",
        "app",
        "scripts/run-pest-locked.sh",
        "php",
        "artisan",
        "test",
        "--compact",
        "--dirty",
      ],
      repo,
      "Pest (--dirty)",
    ),
  );
}

if (process.env.STOP_HOOK_E2E === "1" && lintFiles.length > 0 && hasCommand("pnpm")) {
  tasks.push(runAsync(["pnpm", "test:e2e", "--reporter=dot"], repo, "E2E (Playwright)"));
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

function changedFiles(repo) {
  const tracked = run(["git", "diff", "--name-only", "HEAD"], repo);
  const untracked = run(["git", "ls-files", "--others", "--exclude-standard"], repo);
  const merged = [...tracked.stdout.split("\n"), ...untracked.stdout.split("\n")];
  const all = [...new Set(merged)].filter(Boolean).sort();
  const existing = all.filter((file) => existsSync(join(repo, file)));

  return { all, existing };
}

function byExt(files, extensions) {
  return files.filter((file) => extensions.has(extname(file)));
}

function dockerAppRunning(repo) {
  const result = run(["docker", "compose", "ps", "--services", "--filter", "status=running"], repo);

  return result.status === 0 && result.stdout.split("\n").includes("app");
}

function ensureDockerGitSafeDirectory(repo) {
  const result = run(
    ["docker", "compose", "exec", "-T", "app", "git", "rev-parse", "--show-toplevel"],
    repo,
  );

  if (result.status === 0) {
    return;
  }

  const output = `${result.stdout}\n${result.stderr}`;

  if (!output.includes("dubious ownership")) {
    return;
  }

  const pwd = run(["docker", "compose", "exec", "-T", "app", "pwd"], repo);
  const safeDirectory = pwd.status === 0 ? pwd.stdout.trim() : "/app";

  run(
    [
      "docker",
      "compose",
      "exec",
      "-T",
      "app",
      "git",
      "config",
      "--global",
      "--add",
      "safe.directory",
      safeDirectory,
    ],
    repo,
  );
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

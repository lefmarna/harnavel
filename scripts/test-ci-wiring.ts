import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { parse } from "yaml";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const ACTION_REF = "./.github/actions/ci-lint";
const TEST_REF = "./.github/workflows/reusable-test.yml";

type Step = {
  env?: Record<string, string>;
  id?: string;
  name?: string;
  run?: string;
  uses?: string;
  with?: Record<string, string>;
};

type Job = {
  needs?: string | string[];
  steps?: Step[];
  "timeout-minutes"?: number;
  uses?: string;
};

type Workflow = {
  jobs?: Record<string, Job>;
  runs?: {
    steps?: Step[];
  };
};

type ComposerManifest = {
  scripts?: Record<string, string | string[]>;
};

type PackageManifest = {
  dependencies?: Record<string, string>;
  devDependencies?: Record<string, string>;
  optionalDependencies?: Record<string, string>;
  scripts?: Record<string, string>;
};

function load(relativePath: string): Workflow {
  return (parse(fs.readFileSync(path.join(ROOT, relativePath), "utf8")) ?? {}) as Workflow;
}

function read(relativePath: string): string {
  return fs.readFileSync(path.join(ROOT, relativePath), "utf8");
}

function fail(message: string): never {
  throw new Error(`CI wiring check failed: ${message}`);
}

function usesAction(job: Job | undefined): boolean {
  return job?.steps?.some((step) => step.uses === ACTION_REF) ?? false;
}

function needs(job: Job | undefined, dependency: string): boolean {
  const dependencies = job?.needs;

  return Array.isArray(dependencies)
    ? dependencies.includes(dependency)
    : dependencies === dependency;
}

const action = load(".github/actions/ci-lint/action.yml");
const actionCommands = (action.runs?.steps ?? []).map((step) => step.run ?? "").join("\n");

const packageManifest = JSON.parse(read("package.json")) as PackageManifest;
const lintCommand = packageManifest.scripts?.lint ?? "";
const lintCheckCommand = packageManifest.scripts?.["lint:check"] ?? "";
const lintFixCommand = packageManifest.scripts?.["lint:fix"] ?? "";

if (fs.existsSync(path.join(ROOT, "eslint.config.js"))) {
  fail("ESLint config must not remain after Oxlint becomes the only linter");
}

if (read(".deployignore").includes("eslint.config.js")) {
  fail("deployment config must not retain a removed ESLint file");
}

const eslintPackages = Object.keys({
  ...packageManifest.dependencies,
  ...packageManifest.devDependencies,
}).filter(
  (name) =>
    name === "typescript-eslint" ||
    name === "@stylistic/eslint-plugin" ||
    name.startsWith("@eslint/") ||
    name.startsWith("eslint"),
);

if (eslintPackages.length > 0) {
  fail(`ESLint dependencies must be removed: ${eslintPackages.join(", ")}`);
}

const directPlatformBindings = Object.keys(packageManifest.optionalDependencies ?? {}).filter(
  (name) =>
    name.startsWith("@rollup/rollup-") ||
    name.startsWith("@tailwindcss/oxide-") ||
    name.startsWith("lightningcss-"),
);

if (directPlatformBindings.length > 0) {
  fail(
    `platform bindings owned by Vite and Tailwind must stay transitive: ${directPlatformBindings.join(", ")}`,
  );
}

if (!lintCommand.includes("lint:check") || lintCommand.includes("--fix")) {
  fail("lint must be a non-mutating alias of lint:check");
}

if (
  !lintCheckCommand.includes("oxlint") ||
  !lintCheckCommand.includes("--type-aware") ||
  lintCheckCommand.includes("--fix")
) {
  fail("lint:check must run type-aware Oxlint without mutating files");
}

if (
  !lintFixCommand.includes("oxlint") ||
  !lintFixCommand.includes("--fix") ||
  lintFixCommand.includes("--fix-suggestions") ||
  lintFixCommand.includes("--fix-dangerously")
) {
  fail("lint:fix must use only Oxlint safe fixes");
}

for (const command of [
  "vendor/bin/pint --test",
  "composer analyse",
  "pnpm run format:check",
  "pnpm run lint:check",
  "pnpm run types:check",
  "pnpm run lint:docs",
  "pnpm run test:unit",
  "pnpm run test:deploy-lifecycle",
  "pnpm run test:deploy-rsync-excludes",
  "pnpm run test:toolchain-versions",
]) {
  if (!actionCommands.includes(command)) {
    fail(`ci-lint must run ${JSON.stringify(command)}`);
  }
}

const check = load(".github/workflows/check.yml").jobs;

if (!usesAction(check?.check)) {
  fail("check.yml must use ci-lint");
}

const quality = load(".github/workflows/quality.yml").jobs;

if (!usesAction(quality?.quality)) {
  fail("quality.yml must use ci-lint");
}

if (quality?.test?.uses !== TEST_REF || !needs(quality.test, "quality")) {
  fail("quality.yml test must use reusable-test after ci-lint");
}

const delivery = load(".github/workflows/delivery.yml").jobs;

if (!usesAction(delivery?.lint)) {
  fail("delivery.yml must use ci-lint");
}

if (
  delivery?.test?.uses !== TEST_REF ||
  !needs(delivery.test, "lint") ||
  !needs(delivery?.deploy, "test")
) {
  fail("delivery must run reusable-test after ci-lint and before deploy");
}

const mutation = load(".github/workflows/mutation.yml").jobs;
const mutationCommands = (mutation?.mutation?.steps ?? []).map((step) => step.run ?? "").join("\n");

if (mutation?.mutation?.["timeout-minutes"] !== 30) {
  fail("mutation job must stop after 30 minutes");
}

if (
  !mutationCommands.includes("--mutate") ||
  !mutationCommands.includes("--min=100") ||
  mutationCommands.includes("--class")
) {
  fail("mutation must use test-level mutates() declarations with --min=100");
}

const reusableTestSteps = load(".github/workflows/reusable-test.yml").jobs?.test?.steps ?? [];
const createE2eDatabase = reusableTestSteps.find((step) => step.name === "Create E2E Database");
const runE2e = reusableTestSteps.find((step) => step.name === "Run E2E Tests");

if (!createE2eDatabase?.run?.includes("app_e2e")) {
  fail("reusable-test must create the dedicated E2E database");
}

if (runE2e?.env?.DB_DATABASE !== "app_e2e") {
  fail("reusable-test must run Playwright against the dedicated E2E database");
}

// Composer resolves its cache directory from COMPOSER_HOME and the XDG settings,
// so any hardcoded path risks pointing at nothing: actions/cache then warns and
// saves nothing, and every job refetches every package from api.github.com. Ask
// Composer where it actually caches, before the step that reads the answer.
const composerCacheOwners: [string, Step[]][] = [
  [".github/actions/ci-lint/action.yml", action.runs?.steps ?? []],
  [".github/workflows/reusable-test.yml", reusableTestSteps],
  [".github/workflows/mutation.yml", mutation?.mutation?.steps ?? []],
];

for (const [file, steps] of composerCacheOwners) {
  const cacheIndex = steps.findIndex((step) => step.name === "Cache Composer packages");
  const cacheStep = steps[cacheIndex];

  if (!cacheStep) {
    fail(`${file} must cache Composer packages`);
  }

  const reference = /^\$\{\{ steps\.([\w-]+)\.outputs\.([\w-]+) \}\}$/.exec(
    cacheStep.with?.path ?? "",
  );
  const resolverId = reference?.[1];
  const output = reference?.[2];

  if (resolverId === undefined || output === undefined) {
    fail(`${file} must cache the Composer directory resolved at runtime, not a hardcoded path`);
  }

  const resolverIndex = steps.findIndex((step) => step.id === resolverId);

  if (!steps[resolverIndex]?.run?.includes(`${output}=$(composer config cache-files-dir)`)) {
    fail(`${file} must publish composer config cache-files-dir as ${JSON.stringify(output)}`);
  }

  if (resolverIndex > cacheIndex) {
    fail(`${file} must resolve the Composer cache directory before the cache step`);
  }
}

// The list above cannot see a workflow added later, so pin the regression itself.
const legacyComposerCache = fs
  .readdirSync(path.join(ROOT, ".github"), { encoding: "utf8", recursive: true })
  .filter(
    (entry) =>
      /\.ya?ml$/.test(entry) && read(path.join(".github", entry)).includes(".composer/cache"),
  );

if (legacyComposerCache.length > 0) {
  fail(`the Composer 1 cache path must not come back: ${legacyComposerCache.join(", ")}`);
}

const pestLockPath = "scripts/run-pest-locked.sh";
const mise = read("mise.toml");
const miseCommands = mise.split("\n").filter((line) => {
  const trimmed = line.trimStart();

  return !trimmed.startsWith("#") && !trimmed.startsWith("description =");
});

const composer = JSON.parse(read("composer.json")) as ComposerManifest;
const composerCommands = Object.values(composer.scripts ?? {}).flatMap((commands) =>
  Array.isArray(commands) ? commands : [commands],
);
const unguardedPestCommands = [...miseCommands, ...composerCommands].filter(
  (line) =>
    (line.includes("php artisan test") || line.includes("vendor/bin/pest")) &&
    !line.includes(pestLockPath),
);

if (unguardedPestCommands.length > 0) {
  fail("local Pest tasks must reject concurrent DB-backed processes");
}

const stopHook = read("scripts/hooks/stop-verify.mjs");

if (!stopHook.includes(pestLockPath)) {
  fail("the stop hook must reject a concurrent DB-backed Pest process");
}

const lefthook = read("lefthook.yml");

for (const command of [
  "pnpm exec oxlint --fix --no-error-on-unmatched-pattern {staged_files}",
  "pnpm exec oxfmt --no-error-on-unmatched-pattern {staged_files}",
  "pnpm test:unit",
]) {
  if (!lefthook.includes(command)) {
    fail(`the pre-commit hook must run ${command}`);
  }
}

if (lefthook.includes("eslint")) {
  fail("the pre-commit hook must use Oxlint as its only linter");
}

if (!fs.existsSync(path.join(ROOT, pestLockPath)) || !read(pestLockPath).includes("flock -n 9")) {
  fail("the shared Pest runner must acquire a non-blocking lock");
}

console.log("OK: required checks gate pull requests and deployments.");

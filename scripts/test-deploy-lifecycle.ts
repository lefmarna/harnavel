import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { parse } from "yaml";

// XServer の上書き配備では /up だけで途中状態の安全性を証明できないため、TADR-0001 の配備契約を検証する。

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const WORKFLOW =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ?? path.join(ROOT, ".github/workflows/delivery.yml");

type Step = {
  name?: string;
  run?: string;
  uses?: string;
  if?: string;
  "continue-on-error"?: boolean | string;
};
type Job = {
  needs?: string | string[];
  uses?: string;
  with?: Record<string, unknown>;
  steps?: Step[];
};
type Workflow = {
  concurrency?: { "cancel-in-progress"?: boolean };
  jobs?: Record<string, Job>;
};

const ORDERED = [
  "Enable maintenance mode",
  "Deploy to XServer via Rsync",
  "Run post-deploy commands",
  "Wait for OPcache revalidation",
  "Disable maintenance mode",
  "Verify release health",
];

function fail(message: string): never {
  throw new Error(`Deployment lifecycle check failed: ${message}`);
}

const workflow = parse(fs.readFileSync(WORKFLOW, "utf8")) as Workflow;
const jobs = workflow.jobs ?? {};
const steps = jobs.deploy?.steps ?? [];

function indexOf(name: string): number {
  const index = steps.findIndex((step) => step.name === name);

  return index === -1 ? fail(`missing step "${name}"`) : index;
}

// YAML の run: true は文字列ではなく真偽値になるため、無効化されたステップを拒否する。
function runOf(name: string): string {
  const run: unknown = steps[indexOf(name)]?.run;

  return typeof run === "string" ? run : fail(`step "${name}" has no run script`);
}

function requireText(name: string, expected: string, why: string): void {
  if (!runOf(name).includes(expected)) {
    fail(`step "${name}" must ${why} (${expected})`);
  }
}

for (let index = 1; index < ORDERED.length; index++) {
  const before = ORDERED[index - 1] ?? "";
  const after = ORDERED[index] ?? "";

  if (indexOf(before) >= indexOf(after)) {
    fail(`"${before}" must run before "${after}"`);
  }
}

// ジョブ構成が変わっても、配備が全ゲートの完了後になることを依存関係全体で確認する。
function upstreamOf(job: string, seen = new Set<string>()): Set<string> {
  for (const dependency of [jobs[job]?.needs ?? []].flat()) {
    if (!seen.has(dependency)) {
      seen.add(dependency);
      upstreamOf(dependency, seen);
    }
  }

  return seen;
}

const upstream = upstreamOf("deploy");

if (!upstream.has("quality")) {
  fail('the deploy job must wait for "quality"');
}

const QUALITY_WORKFLOW = "./.github/workflows/reusable-quality.yml";

if (jobs.quality?.uses !== QUALITY_WORKFLOW) {
  fail(`the delivery quality job must run ${QUALITY_WORKFLOW}`);
}

const e2e = jobs.quality?.with?.e2e;

if (e2e !== undefined && e2e !== true) {
  fail("the delivery quality job must run E2E itself when no verified build exists");
}

const quality = parse(fs.readFileSync(path.join(ROOT, QUALITY_WORKFLOW), "utf8")) as Workflow;
const qualitySteps = quality.jobs?.quality?.steps ?? [];
const uploadAt = qualitySteps.findIndex(
  (step) =>
    step.uses?.startsWith("actions/upload-artifact@") && step.name === "Upload build artifacts",
);

if (uploadAt === -1 || qualitySteps[uploadAt]?.if !== undefined) {
  fail("the verified build must only be uploaded after successful quality checks");
}

for (const command of [
  "vendor/bin/pint --test",
  "composer analyse",
  "pnpm run format:check",
  "pnpm run lint:check",
  "pnpm run types:check",
  "pnpm run test:unit",
  "./vendor/bin/pest --coverage",
  "pnpm test:e2e",
]) {
  const at = qualitySteps.findIndex(
    (step) => step.run === command || step.run?.startsWith(`${command} `),
  );
  const step = qualitySteps[at];
  if (
    at === -1 ||
    at >= uploadAt ||
    (step?.["continue-on-error"] !== undefined && step["continue-on-error"] !== false) ||
    (step?.if !== undefined && !(command === "pnpm test:e2e" && step.if === "inputs.e2e"))
  ) {
    fail(`the verified build must follow a required quality check: ${command}`);
  }
}

if (workflow.concurrency?.["cancel-in-progress"] !== false) {
  fail("active deployments must not be cancelled by a newer push");
}

requireText("Enable maintenance mode", "artisan down", "take the release into maintenance");

// --render が空だと配備途中の Laravel が起動してしまうため、事前描画するビュー名を必須にする。
for (const step of ["Enable maintenance mode", "Run post-deploy commands"]) {
  if (!/artisan down[^\n]*--render=(?:'[^'\s]+'|"[^"\s]+"|[^\s'"]+)/.test(runOf(step))) {
    fail(`step "${step}" must prerender a named maintenance view (artisan down --render=<view>)`);
  }
}
requireText(
  "Enable maintenance mode",
  "vendor/autoload.php",
  "refuse to boot an incomplete target, leaving an existing maintenance state repairable",
);
requireText("Deploy to XServer via Rsync", "--delete", "replace the release");
requireText("Disable maintenance mode", "artisan up", "lift maintenance");

// 失敗した配備から復旧できるよう artisan down の失敗は許容するが、その場合はメンテナンス状態を再検証する。
const enable = runOf("Enable maintenance mode");

// /up はメンテナンス対象外なので、レスポンダー・描画済みテンプレート・除外設定を個別に確認する。
const PROOF_OF_MAINTENANCE: [RegExp, string][] = [
  [/storage\/framework\/down/, "read the payload back"],
  [/maintenance\.php/, "confirm the prerendered responder is in place"],
  [/json_decode|json\.load|jq\b/, "parse the payload rather than trust its presence"],
  [/template/, "reject an empty prerendered template"],
  [/except/, "reject an exclusion list that would let requests through"],
];

if (/artisan down[^\n]*\|\|/.test(enable.replaceAll(/'[^']*'/g, ""))) {
  for (const [pattern, why] of PROOF_OF_MAINTENANCE) {
    if (!pattern.test(enable)) {
      fail(`an absorbed maintenance failure must ${why} before the release is replaced`);
    }
  }
}

// 待機時間は配備設定が定めるので、メンテナンス中に待つことだけを確かめる。
if (!/\bsleep\s+[1-9]/.test(runOf("Wait for OPcache revalidation"))) {
  fail("the OPcache window must be waited out while maintenance is still active");
}

// リダイレクトや 204 を正常扱いにすると配備失敗を見逃すため、正常判定は HTTP 200 に限定する。
const health = runOf("Verify release health");
const accepted = [...health.matchAll(/=\s*"?(\d{2,3})"?/g)].map((match) => match[1]);

if (accepted.length !== 1 || accepted[0] !== "200") {
  fail("release health must be decided by an exact match on HTTP 200 and no other status");
}

const postDeploy = runOf("Run post-deploy commands");
const composerAt = postDeploy.indexOf("composer");
const downAt = postDeploy.indexOf("artisan down");
const migrateAt = postDeploy.indexOf("artisan migrate");

if (composerAt === -1 || downAt === -1 || migrateAt === -1) {
  fail("post-deploy must install dependencies, re-enter maintenance, and migrate");
}

if (!(composerAt < downAt && downAt < migrateAt)) {
  fail(
    "post-deploy must re-enter maintenance after Composer rewrites vendor/ and before migrating",
  );
}

// PHP 文字列内の区切り文字を除外し、改行を含めて失敗時に後続コマンドへ進まないことを確認する。
const chain = postDeploy
  .slice(downAt, migrateAt)
  .replaceAll(/'[^']*'/g, "")
  .replaceAll("&&", "")
  .replaceAll("\\\n", "");

if (/[;|&]/.test(chain) || chain.includes("\n")) {
  fail("a failed maintenance command must short-circuit the migration; keep the chain &&-linked");
}

console.log("OK: deploy order, gate dependencies, and the maintenance contract are intact.");

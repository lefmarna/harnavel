import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { parse } from "yaml";

// XServer releases replace the live directory, so a deploy that loses maintenance, boots an
// incomplete target, serves stale OPcache, or runs before its gates still reports green. Only
// those contracts are checked here; retry counts, timeouts, and payload literals are the
// workflow's own business (TADR-0001, TADR-0006).

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const WORKFLOW =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ?? path.join(ROOT, ".github/workflows/delivery.yml");

type Step = { name?: string; run?: string; uses?: string };
type Job = { needs?: string | string[]; uses?: string; steps?: Step[] };
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

// YAML parses an unquoted `run: true` as a boolean, so a neutered step arrives as a non-string.
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

// Reachability rather than single edges: production must never be replaced by a release whose
// gates have not finished, however the job graph is later restructured.
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

for (const gate of ["lint", "test"]) {
  if (!upstream.has(gate)) {
    fail(`the deploy job must wait for "${gate}"`);
  }
}

// Waiting for a gate binds nothing if delivery stops running it. Pull requests run the same
// action through quality.yml, so a delivery job that drops it is caught before it can ship.
const GATE_ACTION = "./.github/actions/ci-lint";
const TEST_WORKFLOW = "./.github/workflows/reusable-test.yml";

if (!(jobs.lint?.steps ?? []).some((step) => step.uses === GATE_ACTION)) {
  fail(`the delivery lint job must run ${GATE_ACTION}`);
}

if (jobs.test?.uses !== TEST_WORKFLOW) {
  fail(`the delivery test job must run ${TEST_WORKFLOW}, not merely carry the name`);
}

if (workflow.concurrency?.["cancel-in-progress"] !== false) {
  fail("active deployments must not be cancelled by a newer push");
}

requireText("Enable maintenance mode", "artisan down", "take the release into maintenance");

// Laravel's maintenance stub returns and lets the framework boot when the payload carries no
// prerendered template, so an unrendered `artisan down` boots an incomplete target mid-release.
// An empty `--render=` is falsy in PHP and leaves the same null template, so the view matters.
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

// The entry may absorb its own failure so that a release already down from a failed deploy stays
// repairable (TADR-0006). That tolerance is only safe while the state is verified afterwards, so
// this binds the absorbing form alone; letting `artisan down` fail hard needs no proof.
const enable = runOf("Enable maintenance mode");

// Maintenance protects a release only when the responder is in place and the payload names a
// template and a narrow exclusion list; `file_exists` on the down file proves none of that, and
// `/up` is exempted before the payload is read, so a broken state still answers health with 200.
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

// Timestamp revalidation keeps serving the previous code for a moment after the files change, so
// maintenance is held across that window (docs/infrastructure.md). The length is not fixed here.
if (!/\bsleep\s+[1-9]/.test(runOf("Wait for OPcache revalidation"))) {
  fail("the OPcache window must be waited out while maintenance is still active");
}

// A release that fails after `artisan up` has to be reported as a failed deploy. Widening the
// verdict is the tempting fix once the endpoint starts redirecting — a status range, or just one
// more code waved through — and it passes 204 or 301 as healthy. So the script may compare the
// status against 200 and against nothing else; retries and timeouts stay the workflow's business.
const health = runOf("Verify release health");
const accepted = [...health.matchAll(/=\s*"?(\d{2,3})"?/g)].map((match) => match[1]);

if (accepted.length !== 1 || accepted[0] !== "200") {
  fail("release health must be decided by an exact match on HTTP 200 and no other status");
}

// Composer install rewrites vendor/, so maintenance is re-established before migrations run, and
// a failed re-entry has to stop the chain rather than migrate an unprotected release.
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

// Single-quoted PHP payloads carry their own separators, so compare what is left once they and the
// legitimate `&&` links are removed. A line break without a continuation separates commands too.
const chain = postDeploy
  .slice(downAt, migrateAt)
  .replaceAll(/'[^']*'/g, "")
  .replaceAll("&&", "")
  .replaceAll("\\\n", "");

if (/[;|&]/.test(chain) || chain.includes("\n")) {
  fail("a failed maintenance command must short-circuit the migration; keep the chain &&-linked");
}

console.log("OK: deploy order, gate dependencies, and the maintenance contract are intact.");

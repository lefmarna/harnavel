import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { beforeEach, describe, it } from "node:test";
import { runInNewContext } from "node:vm";

import { parse } from "yaml";

// TADR-0001 の配備ゲート。delivery.yml の判定スクリプトをそのまま実行する。

const ROOT = path.resolve(import.meta.dirname, "..");
const REPO = "owner/app";
const SHA = "push-sha";
const TREE = "tree-sha";
const BUCKET = "ci-bucket";
const PROOF = `gs://${BUCKET}/${REPO}/e2e/${TREE}.json`;

type Workflow = {
  jobs: {
    provenance: { steps: { id?: string; run?: string }[] };
    deploy: { if: string };
  };
};

const workflow = parse(
  fs.readFileSync(path.join(ROOT, ".github/workflows/delivery.yml"), "utf8"),
) as Workflow;
const lookup = workflow.jobs.provenance.steps.find((step) => step.id === "lookup")?.run ?? "";

const deployCondition = workflow.jobs.deploy.if.replaceAll(".verified-run", '["verified-run"]');

describe("delivery の配備ゲート", function () {
  for (const [title, quality, verifiedRun, provenanceResult, cancelled, expected] of [
    ["品質検証が通れば配備する", "success", "", "success", false, true],
    ["検証済みビルドがあり品質検証を省いた場合は配備する", "skipped", "12", "success", false, true],
    ["品質検証も検証済みビルドもなければ配備しない", "skipped", "", "success", false, false],
    ["品質検証が失敗したら配備しない", "failure", "", "success", false, false],
    [
      "検証済みビルドがあっても品質検証の失敗を無視しない",
      "failure",
      "12",
      "success",
      false,
      false,
    ],
    ["品質検証がキャンセルされたら配備しない", "cancelled", "12", "success", false, false],
    ["出所の確認が失敗したら配備しない", "success", "12", "failure", false, false],
    ["出所の確認が省かれたら配備しない", "success", "12", "skipped", false, false],
    ["実行全体がキャンセルされたら配備しない", "success", "12", "success", true, false],
  ] as const) {
    it(title, function () {
      assert.equal(
        runInNewContext(deployCondition, {
          cancelled: () => cancelled,
          needs: {
            provenance: { result: provenanceResult, outputs: { "verified-run": verifiedRun } },
            quality: { result: quality },
          },
        }),
        expected,
      );
    });
  }
});

// gh と gcloud の応答だけを差し替え、--jq は本物の jq で評価する。
const GH_STUB = `#!/bin/bash
[ "$1" = api ] || exit 1
shift
while [ $# -gt 0 ]; do
  case "$1" in
    --jq) expr=$2; shift 2 ;;
    --paginate) shift ;;
    *) api=$1; shift ;;
  esac
done
node -e 'const r = JSON.parse(require("fs").readFileSync(process.env.FIXTURE, "utf8")).api[process.argv[1]]; if (r === undefined) process.exit(1); process.stdout.write(JSON.stringify(r))' "$api" |
  jq -r "$expr"
`;

const GCLOUD_STUB = `#!/bin/bash
[ "$1 $2" = "storage cat" ] || exit 1
node -e 'const r = JSON.parse(require("fs").readFileSync(process.env.FIXTURE, "utf8")).storage[process.argv[1]]; if (r === undefined) process.exit(1); process.stdout.write(r)' "$3"
`;

type Run = { event: string; head_sha: string; path?: string; conclusion?: string };
type Fixture = {
  api: Record<string, unknown>;
  storage: Record<string, string>;
};

let dir: string;
let fixture: Fixture;

beforeEach(function () {
  dir = fs.mkdtempSync(path.join(os.tmpdir(), "delivery-provenance-"));
  fs.writeFileSync(path.join(dir, "gh"), GH_STUB, { mode: 0o755 });
  fs.writeFileSync(path.join(dir, "gcloud"), GCLOUD_STUB, { mode: 0o755 });
  fixture = {
    api: {
      [`repos/${REPO}/git/commits/${SHA}`]: { tree: { sha: TREE } },
      [`repos/${REPO}/commits/${SHA}/pulls`]: [
        { number: 7, merge_commit_sha: SHA, head: { sha: "pr-head" } },
      ],
      [`repos/${REPO}/issues/7/events`]: [],
    },
    storage: {},
  };
});

const artifact = (name: string, runs: Record<number, Run>) => {
  fixture.api[`repos/${REPO}/actions/artifacts?name=${name}&per_page=100`] = {
    artifacts: Object.keys(runs).map((id) => ({
      expired: false,
      workflow_run: { id: Number(id) },
    })),
  };

  for (const [id, run] of Object.entries(runs)) {
    fixture.api[`repos/${REPO}/actions/runs/${id}`] = {
      status: "completed",
      conclusion: run.conclusion ?? "success",
      path: run.path ?? ".github/workflows/quality.yml",
      event: run.event,
      head_sha: run.head_sha,
      html_url: `https://example.test/runs/${id}`,
      repository: { id: 1 },
      head_repository: { id: 1 },
    };
  }
};

const provenance = (bucket = BUCKET) => {
  fs.writeFileSync(path.join(dir, "fixture.json"), JSON.stringify(fixture));
  const output = path.join(dir, "output");
  fs.writeFileSync(output, "");
  const result = spawnSync("bash", ["-e", "-c", lookup], {
    encoding: "utf8",
    env: {
      ...process.env,
      PATH: `${dir}:${process.env.PATH}`,
      FIXTURE: path.join(dir, "fixture.json"),
      GITHUB_REPOSITORY: REPO,
      GITHUB_SHA: SHA,
      GITHUB_OUTPUT: output,
      GITHUB_STEP_SUMMARY: path.join(dir, "summary"),
      E2E_RECORD_BUCKET: bucket,
    },
  });
  assert.equal(result.status, 0, result.stderr);

  return Object.fromEntries(
    fs
      .readFileSync(output, "utf8")
      .trim()
      .split("\n")
      .map((line) => line.split("=") as [string, string]),
  );
};

const proof = (fields: object) =>
  JSON.stringify({ repository: REPO, tree: TREE, build: "b", ...fields });

describe("delivery が再利用する検証済みビルド", function () {
  it("E2E を CircleCI に任せたビルドは、同じツリーの E2E 記録があれば再利用する", function () {
    artifact(`build-${TREE}-needs-e2e`, { 12: { event: "pull_request", head_sha: "pr-head" } });
    fixture.storage[PROOF] = proof({});

    assert.deepEqual(provenance(), {
      tree: TREE,
      artifact: `build-${TREE}-needs-e2e`,
      "verified-run": "12",
    });
  });

  it("E2E 記録を読めなければ、E2E を省いたビルドを再利用しない", function () {
    artifact(`build-${TREE}-needs-e2e`, { 12: { event: "pull_request", head_sha: "pr-head" } });

    assert.deepEqual(provenance(), { tree: TREE, artifact: `build-${TREE}` });
  });

  it("別のツリーやリポジトリの E2E 記録では再利用しない", function () {
    artifact(`build-${TREE}-needs-e2e`, { 12: { event: "pull_request", head_sha: "pr-head" } });

    for (const fields of [{ tree: "other-tree" }, { repository: "owner/other" }, {}]) {
      fixture.storage[PROOF] = Object.keys(fields).length ? proof(fields) : "not json";
      assert.deepEqual(provenance(), { tree: TREE, artifact: `build-${TREE}` });
    }
  });

  it("CircleCI を使わないリポジトリでは、E2E を省いたビルドを再利用しない", function () {
    artifact(`build-${TREE}-needs-e2e`, { 12: { event: "pull_request", head_sha: "pr-head" } });
    fixture.storage[PROOF] = proof({});

    assert.deepEqual(provenance(""), { tree: TREE, artifact: `build-${TREE}` });
  });

  it("E2E 記録があっても、マージされた PR の最終 head 以外のビルドは再利用しない", function () {
    artifact(`build-${TREE}-needs-e2e`, {
      12: { event: "pull_request", head_sha: "older-head" },
      13: { event: "pull_request", head_sha: "pr-head", conclusion: "failure" },
    });
    fixture.storage[PROOF] = proof({});

    assert.deepEqual(provenance(), { tree: TREE, artifact: `build-${TREE}` });
  });
});

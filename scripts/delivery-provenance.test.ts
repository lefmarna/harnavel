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

type Step = { id?: string; run?: string; env?: Record<string, string> };
type Workflow = {
  jobs: {
    provenance: { if: string; steps: Step[] };
    audit?: { steps: Step[] };
    deploy: { if: string; environment: string };
  };
};

const workflow = parse(
  fs.readFileSync(path.join(ROOT, ".github/workflows/delivery.yml"), "utf8"),
) as Workflow;
const lookup = workflow.jobs.provenance.steps.find((step) => step.id === "lookup")?.run ?? "";
const auditStep = workflow.jobs.audit?.steps.find((step) => step.id === "audit");

const deployCondition = workflow.jobs.deploy.if.replaceAll(".verified-run", '["verified-run"]');
const provenanceCondition = workflow.jobs.provenance.if.replace(/^\$\{\{(.*)\}\}$/s, "$1");

describe("delivery を起動できるブランチ", function () {
  for (const [title, ref, isTemplate, expected] of [
    ["main は配備する", "refs/heads/main", false, true],
    ["develop は配備する", "refs/heads/develop", false, true],
    [
      "手動で起動しても、ほかのブランチは Develop 環境へ配備しない",
      "refs/heads/feature",
      false,
      false,
    ],
    ["テンプレートリポジトリでは配備しない", "refs/heads/main", true, false],
  ] as const) {
    it(title, function () {
      assert.equal(
        runInNewContext(provenanceCondition, {
          github: { ref, event: { repository: { is_template: isTemplate } } },
        }),
        expected,
      );
    });
  }
});

describe("delivery の配備ゲート", function () {
  for (const [title, quality, verifiedRun, provenanceResult, cancelled, expected, audit] of [
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
    [
      "配備中の版にない脆弱性があれば配備しない",
      "skipped",
      "12",
      "success",
      false,
      false,
      "failure",
    ],
    ["脆弱性の確認が省かれたら配備しない", "success", "", "success", false, false, "skipped"],
    [
      "脆弱性の確認がキャンセルされたら配備しない",
      "success",
      "",
      "success",
      false,
      false,
      "cancelled",
    ],
  ] as const) {
    it(title, function () {
      assert.equal(
        runInNewContext(deployCondition, {
          cancelled: () => cancelled,
          needs: {
            provenance: { result: provenanceResult, outputs: { "verified-run": verifiedRun } },
            quality: { result: quality },
            audit: { result: audit ?? "success" },
          },
        }),
        expected,
      );
    });
  }
});

const GH_STUB = `#!/bin/bash
set -o pipefail
[ "$1" = api ] || exit 1
shift
paginate=
while [ $# -gt 0 ]; do
  case "$1" in
    --jq) expr=$2; shift 2 ;;
    --paginate) paginate=1; shift ;;
    *) api=$1; shift ;;
  esac
done
node -e 'const r = JSON.parse(require("fs").readFileSync(process.env.FIXTURE, "utf8")).api[process.argv[1]]; if (r === undefined) process.exit(1); const pages = r.__pages ?? [r]; for (const page of process.argv[2] ? pages : pages.slice(0, 1)) process.stdout.write(JSON.stringify(page) + "\\n")' "$api" "$paginate" |
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

  it("security-fix が検証したビルドは、同じツリーなら再利用する", function () {
    fixture.api[`repos/${REPO}/commits/${SHA}/pulls`] = [];
    fixture.api[`repos/${REPO}/git/commits/fix-head`] = { tree: { sha: TREE } };
    artifact(`build-${TREE}`, {
      12: {
        event: "workflow_dispatch",
        head_sha: "fix-head",
        path: ".github/workflows/security-fix.yml",
      },
    });

    assert.deepEqual(provenance(), { tree: TREE, artifact: `build-${TREE}`, "verified-run": "12" });
  });

  it("lint とテストを通していないワークフローのビルドは再利用しない", function () {
    fixture.api[`repos/${REPO}/commits/${SHA}/pulls`] = [];
    fixture.api[`repos/${REPO}/git/commits/fix-head`] = { tree: { sha: TREE } };
    artifact(`build-${TREE}`, {
      12: {
        event: "workflow_dispatch",
        head_sha: "fix-head",
        path: ".github/workflows/security.yml",
      },
    });

    assert.deepEqual(provenance(), { tree: TREE, artifact: `build-${TREE}` });
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

const RECORD_STUB = `#!/bin/bash
echo "$(basename "$0") $*" >> "$CALLS"
`;

type Deployment = { sha: string; states: string[] };

const auditAgainstDeployed = (
  deployments: Deployment[] | undefined,
  unreadable?: number,
  pageSize = Infinity,
) => {
  const cwd = fs.mkdtempSync(path.join(os.tmpdir(), "delivery-audit-"));
  fs.mkdirSync(path.join(cwd, "scripts/ci"), { recursive: true });
  fs.writeFileSync(path.join(cwd, "scripts/ci/security-audit.sh"), RECORD_STUB, { mode: 0o755 });
  for (const command of ["git", "composer", "pnpm"]) {
    fs.writeFileSync(path.join(dir, command), RECORD_STUB, { mode: 0o755 });
  }

  const list = `repos/${REPO}/deployments?environment=Production&per_page=100`;
  if (deployments) {
    const listed = deployments.map(({ sha }, index) => ({ id: index + 1, sha }));
    const pages = [];
    for (let start = 0; start < listed.length; start += Math.min(pageSize, listed.length)) {
      pages.push(listed.slice(start, start + pageSize));
    }
    fixture.api[list] = { __pages: pages.length ? pages : [[]] };
    deployments.forEach(({ states }, index) => {
      if (index + 1 !== unreadable) {
        fixture.api[`repos/${REPO}/deployments/${index + 1}/statuses?per_page=100`] = states.map(
          (state) => ({ state }),
        );
      }
    });
  }
  fs.writeFileSync(path.join(dir, "fixture.json"), JSON.stringify(fixture));

  const calls = path.join(dir, "calls");
  fs.writeFileSync(calls, "");
  const result = spawnSync("bash", ["-e", "-c", auditStep?.run ?? "exit 1"], {
    cwd,
    encoding: "utf8",
    env: {
      ...process.env,
      PATH: `${dir}:${process.env.PATH}`,
      FIXTURE: path.join(dir, "fixture.json"),
      CALLS: calls,
      GITHUB_REPOSITORY: REPO,
      ENVIRONMENT: "Production",
    },
  });
  return { status: result.status, calls: fs.readFileSync(calls, "utf8").trim().split("\n") };
};

describe("delivery が配備前に比べる脆弱性", function () {
  it("配備先の環境と同じ環境の配備履歴を見る", function () {
    assert.equal(auditStep?.env?.ENVIRONMENT, workflow.jobs.deploy.environment);
  });

  it("配備中の版にない脆弱性だけを拒む。失敗した配備や新しい push の直前のコミットとは比べない", function () {
    const result = auditAgainstDeployed([
      { sha: "failed-sha", states: ["failure", "in_progress"] },
      { sha: "live-sha", states: ["inactive", "success", "in_progress"] },
      { sha: "older-sha", states: ["inactive", "success"] },
    ]);

    assert.equal(result.status, 0);
    assert.deepEqual(result.calls, [
      "git fetch --no-tags --depth=1 origin live-sha",
      "security-audit.sh ratchet FETCH_HEAD",
    ]);
  });

  it("成功した配備が履歴の次のページにあっても、それと比べる", function () {
    const result = auditAgainstDeployed(
      [
        ...Array.from({ length: 100 }, () => ({ sha: "failed-sha", states: ["failure"] })),
        { sha: "live-sha", states: ["success"] },
      ],
      undefined,
      100,
    );

    assert.equal(result.status, 0);
    assert.deepEqual(result.calls, [
      "git fetch --no-tags --depth=1 origin live-sha",
      "security-audit.sh ratchet FETCH_HEAD",
    ]);
  });

  it("まだ配備していなければ、すべての脆弱性を拒む", function () {
    const result = auditAgainstDeployed([{ sha: "failed-sha", states: ["failure"] }]);

    assert.equal(result.status, 0);
    assert.deepEqual(result.calls, ["composer audit --locked --abandoned=report", "pnpm audit"]);
  });

  it("配備履歴を読めなければ失敗する", function () {
    const result = auditAgainstDeployed(undefined);

    assert.notEqual(result.status, 0);
    assert.deepEqual(result.calls, [""]);
  });

  it("配備の状態を読めなければ、古い配備と比べずに失敗する", function () {
    const result = auditAgainstDeployed(
      [
        { sha: "live-sha", states: ["success"] },
        { sha: "older-sha", states: ["success"] },
      ],
      1,
    );

    assert.notEqual(result.status, 0);
    assert.deepEqual(result.calls, [""]);
  });
});

import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { beforeEach, describe, it } from "node:test";
import { runInNewContext } from "node:vm";

import { parse } from "yaml";

// TADR-0001 の配備ゲート。security-fix は検証した修正を main へ直接反映し、本番配備を起動する。

const ROOT = path.resolve(import.meta.dirname, "..");
const REPO = "owner/app";
const VERIFIED = "verified-sha";

type Workflow = { jobs: { verify: { if: string }; merge: { if: string } } };

const workflow = parse(
  fs.readFileSync(path.join(ROOT, ".github/workflows/security-fix.yml"), "utf8"),
) as Workflow;
const expression = (condition: string) => condition.replace(/^\$\{\{(.*)\}\}$/s, "$1");
const format = (template: string, ...args: string[]) =>
  template.replace(/\{(\d+)\}/g, (_, index: string) => args[Number(index)] ?? "");

describe("security-fix が検証するブランチ", function () {
  for (const [title, ref, base, expected] of [
    ["対象ブランチ向けの修正ブランチは検証する", "refs/heads/security-fix/main", "main", true],
    ["別のブランチを対象ブランチへ反映させない", "refs/heads/feature", "main", false],
    [
      "別の対象ブランチ向けの修正ブランチは検証しない",
      "refs/heads/security-fix/develop",
      "main",
      false,
    ],
    ["修正ブランチと同じ名前のタグは検証しない", "refs/tags/security-fix/main", "main", false],
  ] as const) {
    it(title, function () {
      const refName = ref.replace(/^refs\/(heads|tags)\//, "");
      assert.equal(
        runInNewContext(expression(workflow.jobs.verify.if), {
          format,
          github: { ref, ref_name: refName },
          inputs: { base },
        }),
        expected,
      );
    });
  }

  for (const [result, expected] of [
    ["success", true],
    ["failure", false],
    ["cancelled", false],
    ["skipped", false],
  ] as const) {
    it(`検証の結果が ${result} なら${expected ? "反映する" : "反映しない"}`, function () {
      assert.equal(
        runInNewContext(expression(workflow.jobs.merge.if), {
          needs: { verify: { result } },
        }),
        expected,
      );
    });
  }
});

const GH_STUB = `#!/usr/bin/env node
const fs = require("fs");
const { spawnSync } = require("child_process");
const args = process.argv.slice(2);
const fixture = JSON.parse(fs.readFileSync(process.env.FIXTURE, "utf8"));
const call = { args };
if (args[0] === "workflow" && args[1] === "run") {
  fs.appendFileSync(process.env.CALLS, JSON.stringify({ workflow: args[2], ref: args[args.indexOf("--ref") + 1], args }) + "\\n");
  process.exit(0);
}
if (args[0] !== "api") process.exit(1);
let method = "GET", endpoint, jq;
const fields = {};
for (let i = 1; i < args.length; i++) {
  if (args[i] === "-X") method = args[++i];
  else if (args[i] === "--jq") jq = args[++i];
  else if (args[i] === "-f" || args[i] === "-F") { const [k, ...v] = args[++i].split("="); fields[k] = v.join("="); }
  else endpoint = args[i];
}
fs.appendFileSync(process.env.CALLS, JSON.stringify({ method, endpoint, fields }) + "\\n");
const key = method + " " + endpoint;
let response = fixture[key];
if (response && Array.isArray(response.__seq)) {
  const counter = process.env.CALLS + "." + Buffer.from(key).toString("hex");
  const n = fs.existsSync(counter) ? Number(fs.readFileSync(counter, "utf8")) : 0;
  fs.writeFileSync(counter, String(n + 1));
  response = response.__seq[Math.min(n, response.__seq.length - 1)];
}
if (response === undefined) process.exit(1);
const body = JSON.stringify(response);
if (!jq) { process.stdout.write(body); process.exit(0); }
const out = spawnSync("jq", ["-r", jq], { input: body, encoding: "utf8" });
process.stdout.write(out.stdout);
process.exit(out.status);
`;

type Call = {
  method?: string;
  endpoint?: string;
  fields?: Record<string, string>;
  workflow?: string;
  ref?: string;
};

let dir: string;
let fixture: Record<string, unknown>;

const merge = (env: { base: string; isTemplate?: boolean; fails?: boolean }) => {
  fs.writeFileSync(path.join(dir, "fixture.json"), JSON.stringify(fixture));
  const calls = path.join(dir, "calls");
  fs.writeFileSync(calls, "");
  const result = spawnSync("bash", [path.join(ROOT, "scripts/ci/security-merge.sh")], {
    encoding: "utf8",
    env: {
      ...process.env,
      PATH: `${dir}:${process.env.PATH}`,
      FIXTURE: path.join(dir, "fixture.json"),
      CALLS: calls,
      GITHUB_REPOSITORY: REPO,
      GITHUB_SHA: VERIFIED,
      GITHUB_STEP_SUMMARY: path.join(dir, "summary"),
      BASE: env.base,
      DEFAULT_BRANCH: "main",
      IS_TEMPLATE: String(env.isTemplate ?? false),
    },
  });
  if (env.fails) {
    assert.notEqual(result.status, 0);
  } else {
    assert.equal(result.status, 0, result.stderr);
  }

  const all = fs
    .readFileSync(calls, "utf8")
    .trim()
    .split("\n")
    .filter(Boolean)
    .map((line) => JSON.parse(line) as Call);

  return {
    writes: all.filter((call) => call.method !== undefined && call.method !== "GET"),
    dispatched: all.filter((call) => call.workflow).map((call) => `${call.workflow}@${call.ref}`),
  };
};

const compare = (behind: number) => ({
  behind_by: behind,
  base_commit: { commit: { tree: { sha: "old-tree" } } },
});

const upToDate = (base: string) => {
  fixture[`GET repos/${REPO}/compare/${base}...${VERIFIED}`] = compare(0);
  fixture[`GET repos/${REPO}/git/commits/${VERIFIED}`] = {
    tree: { sha: "fixed-tree" },
    parents: [{ sha: "parent-sha" }],
  };
  fixture[`GET repos/${REPO}/git/commits/parent-sha`] = { tree: { sha: "old-tree" } };
  fixture[`PATCH repos/${REPO}/git/refs/heads/${base}`] = { object: { sha: VERIFIED } };
  fixture[`DELETE repos/${REPO}/git/refs/heads/security-fix/${base}`] = {};
};

beforeEach(function () {
  dir = fs.mkdtempSync(path.join(os.tmpdir(), "security-fix-"));
  fs.writeFileSync(path.join(dir, "gh"), GH_STUB, { mode: 0o755 });
  fixture = {};
});

describe("security-fix の反映", function () {
  it("検証後に対象ブランチが進んでいれば反映も配備もせず、修正を作り直す", function () {
    upToDate("main");
    fixture[`GET repos/${REPO}/compare/main...${VERIFIED}`] = compare(1);

    assert.deepEqual(merge({ base: "main" }), { writes: [], dispatched: ["security.yml@main"] });
  });

  it("保護されていないブランチは検証したコミットへ早送りし、そのブランチの配備を起動する", function () {
    upToDate("develop");

    const { writes, dispatched } = merge({ base: "develop" });
    assert.deepEqual(writes[0], {
      method: "PATCH",
      endpoint: `repos/${REPO}/git/refs/heads/develop`,
      fields: { sha: VERIFIED, force: "false" },
    });
    assert.deepEqual(dispatched, ["delivery.yml@develop"]);
  });

  it("早送りの間に対象ブランチが進んだら配備せず、修正を作り直す", function () {
    upToDate("develop");
    delete fixture[`PATCH repos/${REPO}/git/refs/heads/develop`];
    fixture[`GET repos/${REPO}/compare/develop...${VERIFIED}`] = {
      __seq: [compare(0), compare(1)],
    };

    assert.deepEqual(merge({ base: "develop" }).dispatched, ["security.yml@main"]);
  });

  it("対象ブランチが進んでいないのに早送りを拒まれたら、失敗として知らせて配備しない", function () {
    upToDate("main");
    delete fixture[`PATCH repos/${REPO}/git/refs/heads/main`];

    assert.deepEqual(merge({ base: "main", fails: true }).dispatched, []);
  });

  it("main を更新したら本番へ配備し、develop へ取り込むため走査し直す", function () {
    upToDate("main");

    assert.deepEqual(merge({ base: "main" }).dispatched, [
      "delivery.yml@main",
      "security.yml@main",
    ]);
  });

  it("テンプレートリポジトリでは配備を起動しない", function () {
    upToDate("main");

    assert.deepEqual(merge({ base: "main", isTemplate: true }).dispatched, ["security.yml@main"]);
  });

  it("ツリーが変わらなければ配備を起動しない", function () {
    upToDate("develop");
    fixture[`GET repos/${REPO}/git/commits/parent-sha`] = { tree: { sha: "fixed-tree" } };

    assert.deepEqual(merge({ base: "develop" }).dispatched, []);
  });

  it("反映した後で配備を起動できず再実行したときも、配備を起動する", function () {
    upToDate("main");
    fixture[`GET repos/${REPO}/compare/main...${VERIFIED}`] = {
      behind_by: 0,
      base_commit: { commit: { tree: { sha: "fixed-tree" } } },
    };

    assert.deepEqual(merge({ base: "main" }).dispatched, [
      "delivery.yml@main",
      "security.yml@main",
    ]);
  });
});

const AUDIT_STUB = (tool: "composer" | "pnpm", flag: string) => `#!/usr/bin/env node
const fs = require("fs");
const args = process.argv.slice(2);
const target = args[args.indexOf("${flag}") + 1] === "." ? "head" : "base";
const result = JSON.parse(fs.readFileSync(process.env.FIXTURE, "utf8"))["${tool}"][target];
process.stdout.write(result.output);
process.exit(result.status);
`;

type AuditResult = { output: string; status: number };

const composerAudit = (
  advisories: Record<string, readonly string[]>,
  malware: readonly string[] = [],
): AuditResult => {
  const entries = Object.entries(advisories);
  return {
    output: JSON.stringify({
      advisories: entries.length
        ? Object.fromEntries(
            entries.map(([name, ids]) => [
              name,
              ids.map((id) => ({
                advisoryId: id,
                packageName: name,
                severity: "medium",
                title: `${id} title`,
                link: `https://example.test/${id}`,
              })),
            ]),
          )
        : [],
      abandoned: {},
      filter: malware.length
        ? Object.fromEntries(
            malware.map((name) => [
              name,
              [
                {
                  packageName: name,
                  listName: "malware",
                  constraint: "1.0.0",
                  url: `https://example.test/${name}`,
                  reason: "malware",
                  id: `MAL-${name}`,
                  source: "packagist.org",
                },
              ],
            ]),
          )
        : [],
    }),
    status: entries.length || malware.length ? 1 : 0,
  };
};

const pnpmAudit = (advisories: Record<string, readonly string[]>): AuditResult => {
  const list = Object.entries(advisories).flatMap(([name, ids]) =>
    ids.map((id) => ({
      module_name: name,
      github_advisory_id: id,
      severity: "high",
      title: `${id} title`,
      url: `https://example.test/${id}`,
    })),
  );
  return {
    output: JSON.stringify({ advisories: Object.fromEntries(list.map((a, i) => [i, a])) }),
    status: list.length ? 1 : 0,
  };
};

const ratchet = (audits: {
  composer: { head: AuditResult; base: AuditResult };
  pnpm: { head: AuditResult; base: AuditResult };
}) => {
  const repo = fs.mkdtempSync(path.join(os.tmpdir(), "security-audit-repo-"));
  // コミットのフックから実行すると GIT_INDEX_FILE などを引き継ぎ、一時リポジトリの操作が作業ツリーの index を書き換える。
  const env = Object.fromEntries(
    Object.entries(process.env).filter(([key]) => !key.startsWith("GIT_")),
  );
  const git = (...args: string[]) =>
    spawnSync("git", ["-c", "user.name=t", "-c", "user.email=t@invalid", ...args], {
      cwd: repo,
      env,
    });
  git("init", "-q");
  fs.writeFileSync(path.join(repo, "composer.lock"), "{}");
  git("add", ".");
  git("commit", "-q", "-m", "base");

  fs.writeFileSync(path.join(dir, "composer"), AUDIT_STUB("composer", "-d"), { mode: 0o755 });
  fs.writeFileSync(path.join(dir, "pnpm"), AUDIT_STUB("pnpm", "--dir"), { mode: 0o755 });
  fs.writeFileSync(path.join(dir, "fixture.json"), JSON.stringify(audits));

  return spawnSync("bash", [path.join(ROOT, "scripts/ci/security-audit.sh"), "ratchet", "HEAD"], {
    cwd: repo,
    encoding: "utf8",
    env: {
      ...env,
      PATH: `${dir}:${process.env.PATH}`,
      FIXTURE: path.join(dir, "fixture.json"),
    },
  });
};

describe("修正の検証で許す脆弱性", function () {
  it("基準ブランチにも残っている脆弱性だけなら通す", function () {
    const result = ratchet({
      composer: {
        head: composerAudit({ "vendor/a": ["PKSA-a"] }),
        base: composerAudit({ "vendor/a": ["PKSA-a"], "vendor/b": ["PKSA-b"] }),
      },
      pnpm: { head: pnpmAudit({}), base: pnpmAudit({ x: ["GHSA-x"] }) },
    });

    assert.equal(result.status, 0, result.stderr);
  });

  for (const [title, head, base] of [
    [
      "基準ブランチにない Composer の脆弱性があれば通さない",
      { composer: { "vendor/a": ["PKSA-a", "PKSA-new"] }, pnpm: {} },
      { composer: { "vendor/a": ["PKSA-a"] }, pnpm: {} },
    ],
    [
      "基準ブランチにない pnpm の脆弱性があれば通さない",
      { composer: {}, pnpm: { y: ["GHSA-y"] } },
      { composer: {}, pnpm: { x: ["GHSA-x"] } },
    ],
  ] as const) {
    it(title, function () {
      const result = ratchet({
        composer: { head: composerAudit(head.composer), base: composerAudit(base.composer) },
        pnpm: { head: pnpmAudit(head.pnpm), base: pnpmAudit(base.pnpm) },
      });

      assert.notEqual(result.status, 0);
    });
  }

  it("通常の脆弱性が残っていても、基準ブランチにないマルウェアの検出があれば通さない", function () {
    const result = ratchet({
      composer: {
        head: composerAudit({ "vendor/a": ["PKSA-a"] }, ["vendor/evil"]),
        base: composerAudit({ "vendor/a": ["PKSA-a"] }),
      },
      pnpm: { head: pnpmAudit({}), base: pnpmAudit({}) },
    });

    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /vendor\/evil/);
  });

  for (const [title, failure] of [
    ["audit が JSON を返さなければ通さない", { output: "registry error", status: 1 }],
    ["audit が何も出力せずに失敗したら通さない", { output: "", status: 1 }],
    ["脆弱性がないのに audit が失敗したら通さない", { output: '{"advisories":[]}', status: 1 }],
  ] as const) {
    for (const tool of ["composer", "pnpm"] as const) {
      for (const side of ["head", "base"] as const) {
        it(`${title}（${tool}、${side === "head" ? "修正後" : "基準ブランチ"}）`, function () {
          const audits = {
            composer: { head: composerAudit({}), base: composerAudit({}) },
            pnpm: { head: pnpmAudit({}), base: pnpmAudit({}) },
          };
          audits[tool][side] = failure;

          assert.notEqual(ratchet(audits).status, 0);
        });
      }
    }
  }
});

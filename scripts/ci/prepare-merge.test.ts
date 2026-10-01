import assert from "node:assert/strict";
import { execFileSync, spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { beforeEach, describe, it } from "node:test";

const SCRIPT = path.join(import.meta.dirname, "prepare-merge.sh");

// コミットフックが渡す GIT_INDEX_FILE などを引き継がず、開発者の git 設定も読ませない。
const GIT_ENV = {
  ...Object.fromEntries(Object.entries(process.env).filter(([key]) => !key.startsWith("GIT_"))),
  GIT_CONFIG_GLOBAL: "/dev/null",
  GIT_CONFIG_NOSYSTEM: "1",
};

const git = (cwd: string, ...args: string[]): string =>
  execFileSync("git", ["-c", "user.name=Test", "-c", "user.email=test@invalid", ...args], {
    cwd,
    encoding: "utf8",
    env: GIT_ENV,
  }).trim();

const commit = (cwd: string, file: string, content: string): string => {
  fs.writeFileSync(path.join(cwd, file), content);
  git(cwd, "add", file);
  git(cwd, "commit", "-q", "-m", file);

  return git(cwd, "rev-parse", "HEAD");
};

let root: string;
let origin: string;
let work: string;
let checkout: string;

const clone = (head: string) => {
  git(work, "push", "-q", "--all", origin);
  git(root, "clone", "-q", origin, checkout);
  git(checkout, "checkout", "-q", "--detach", head);
};

const prepare = () =>
  spawnSync("bash", [SCRIPT], {
    cwd: checkout,
    encoding: "utf8",
    env: { ...GIT_ENV, BASE_BRANCH: "develop" },
  });

beforeEach(function () {
  root = fs.mkdtempSync(path.join(os.tmpdir(), "prepare-merge-"));
  origin = path.join(root, "origin.git");
  work = path.join(root, "work");
  checkout = path.join(root, "checkout");
  git(root, "init", "-q", "--bare", origin);
  git(root, "init", "-q", "-b", "develop", work);
  commit(work, "app.txt", "base\n");
});

describe("CI で検証する PR の内容", function () {
  it("クローンした後に base へ入った変更も含めたマージ結果を作る", function () {
    git(work, "switch", "-q", "-c", "feature");
    const head = commit(work, "feature.txt", "feature\n");
    git(work, "switch", "-q", "develop");
    clone(head);
    const base = commit(work, "other.txt", "merged by another pull request\n");
    git(work, "push", "-q", origin, "develop");

    const result = prepare();

    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(
      git(checkout, "rev-list", "--parents", "-n", "1", "HEAD").split(" ").slice(1),
      [base, head],
    );
    assert.equal(fs.readFileSync(path.join(checkout, "feature.txt"), "utf8"), "feature\n");
    assert.equal(
      fs.readFileSync(path.join(checkout, "other.txt"), "utf8"),
      "merged by another pull request\n",
    );
  });

  it("最新の base を取得できなければクローン時点の base へマージする", function () {
    git(work, "switch", "-q", "-c", "feature");
    const head = commit(work, "feature.txt", "feature\n");
    git(work, "switch", "-q", "develop");
    const base = commit(work, "other.txt", "already on develop\n");
    clone(head);
    git(checkout, "remote", "set-url", "origin", path.join(root, "missing.git"));

    const result = prepare();

    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(
      git(checkout, "rev-list", "--parents", "-n", "1", "HEAD").split(" ").slice(1),
      [base, head],
    );
  });
});

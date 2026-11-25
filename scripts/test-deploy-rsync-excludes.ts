import { execFileSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import { parse } from "yaml";

// ホストに rsync を要求するため、コミットフックが実行する test:unit とは独立させる。

const ROOT = path.resolve(import.meta.dirname, "..");

const PROTECTED_PATHS = [
  ".env",
  ".user.ini",
  "public/storage",
  "vendor/autoload.php",
  "storage/app/private/user-upload.txt",
  "storage/app/public/user-upload.txt",
  "storage/logs/laravel.log",
  "storage/framework/cache/data/production-cache.php",
  "storage/framework/down",
  "storage/framework/maintenance.php",
  "storage/framework/sessions/session-id",
  "storage/framework/views/compiled.php",
  "dev.example.com/index.html",
];

type Workflow = { jobs?: { deploy?: { steps?: { name?: string; run?: string }[] } } };

function writeFixtureFile(root: string, relativePath: string): void {
  const filePath = path.join(root, relativePath);
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, `${relativePath}\n`);
}

function main(): void {
  const workflow = parse(
    fs.readFileSync(path.join(ROOT, ".github/workflows/delivery.yml"), "utf8"),
  ) as Workflow;
  const rsyncRun =
    workflow.jobs?.deploy?.steps?.find((step) => step.name === "Deploy to XServer via Rsync")
      ?.run ?? "";
  const excludes = [...rsyncRun.matchAll(/--exclude='([^']+)'/g)].map((match) => match[1] ?? "");

  if (excludes.length === 0) {
    throw new Error("No rsync --exclude entries were found in the deploy step.");
  }

  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), "deploy-rsync-excludes-"));

  try {
    const source = path.join(tmp, "source");
    const remote = path.join(tmp, "remote");
    writeFixtureFile(source, "index.php");
    writeFixtureFile(remote, "stale-unprotected.txt");

    for (const protectedPath of PROTECTED_PATHS) {
      writeFixtureFile(remote, protectedPath);
    }

    const output = execFileSync(
      "rsync",
      [
        "-ain",
        "--delete",
        ...excludes.map((exclude) => `--exclude=${exclude}`),
        `${source}/`,
        `${remote}/`,
      ],
      { encoding: "utf8" },
    );

    if (!output.includes("stale-unprotected.txt")) {
      throw new Error("Expected rsync --delete dry-run to delete stale-unprotected.txt");
    }

    for (const protectedPath of PROTECTED_PATHS) {
      if (output.includes(protectedPath)) {
        throw new Error(`Protected path appeared in rsync --delete dry-run: ${protectedPath}`);
      }
    }

    console.log(`OK: ${PROTECTED_PATHS.length} remote paths protected.`);
  } finally {
    fs.rmSync(tmp, { force: true, recursive: true });
  }
}

main();

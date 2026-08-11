import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { parse } from "yaml";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const WORKFLOW =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ?? path.join(ROOT, ".github/workflows/delivery.yml");

const VALID_MAINTENANCE_PAYLOAD = {
  except: ["/up"],
  template: "errors::503",
  status: 503,
  retry: 60,
  redirect: null,
  refresh: null,
  secret: null,
};

type Step = {
  name?: string;
  run?: string;
};

type Workflow = {
  concurrency?: {
    "cancel-in-progress"?: boolean;
  };
  jobs?: {
    deploy?: {
      steps?: Step[];
    };
  };
};

function fail(message: string): never {
  throw new Error(`Deployment lifecycle check failed: ${message}`);
}

const workflow = parse(fs.readFileSync(WORKFLOW, "utf8")) as Workflow;
const steps = workflow.jobs?.deploy?.steps ?? [];

function indexOf(name: string): number {
  const index = steps.findIndex((step) => step.name === name);

  return index === -1 ? fail(`missing step "${name}"`) : index;
}

function runOf(name: string): string {
  return steps[indexOf(name)]?.run ?? fail(`step "${name}" has no run script`);
}

function requireText(name: string, expected: string): void {
  if (!runOf(name).includes(expected)) {
    fail(`step "${name}" must contain ${JSON.stringify(expected)}`);
  }
}

function asLocalRemoteCommand(run: string, start: string): string {
  const remoteStart = run.indexOf(start);
  const sshStart = run.lastIndexOf("ssh ", remoteStart);

  if (remoteStart === -1 || sshStart === -1) {
    fail(`could not locate remote command starting with ${JSON.stringify(start)}`);
  }

  return (run.slice(0, sshStart) + "sh -c " + run.slice(remoteStart)).replaceAll(
    'sleep "$((attempt * 30))"',
    ":",
  );
}

const ordered = [
  "Validate deployment configuration",
  "Check release-critical files",
  "Setup SSH key",
  "Verify XServer toolchain",
  "Enable maintenance mode",
  "Deploy to XServer via Rsync",
  "Run post-deploy commands",
  "Wait for OPcache revalidation",
  "Disable maintenance mode",
  "Verify release health",
];

for (let index = 1; index < ordered.length; index++) {
  const before = ordered[index - 1] ?? "";
  const after = ordered[index] ?? "";

  if (indexOf(before) >= indexOf(after)) {
    fail(`"${before}" must run before "${after}"`);
  }
}

if (workflow.concurrency?.["cancel-in-progress"] !== false) {
  fail("active deployments must not be cancelled by a newer push");
}

requireText("Enable maintenance mode", "vendor/autoload.php");
requireText("Enable maintenance mode", "storage/framework/down");
requireText("Enable maintenance mode", "storage/framework/maintenance.php");
requireText("Enable maintenance mode", "maintenance-mode.stub");
requireText("Enable maintenance mode", "cmp -s");
requireText("Enable maintenance mode", "json_decode");
requireText("Enable maintenance mode", "template");
requireText("Enable maintenance mode", "artisan down");
requireText("Enable maintenance mode", "--render='errors::503'");
requireText("Enable maintenance mode", "--retry=60");

if (/\[\s+!\s+-[efrs]\s+[^\]]*storage\/framework\/down/.test(runOf("Enable maintenance mode"))) {
  fail("existing maintenance mode must be refreshed and validated");
}

const maintenanceRun = runOf("Enable maintenance mode");
const maintenanceFixtureCommandTemplate = asLocalRemoteCommand(maintenanceRun, '"mkdir -p');
const postDeployFixtureCommandTemplate = asLocalRemoteCommand(
  runOf("Run post-deploy commands"),
  '"cd --',
);
const maintenanceFixture = fs.mkdtempSync(path.join(os.tmpdir(), "deploy-lifecycle-"));

try {
  const deployDirectory = path.join(maintenanceFixture, "deploy");
  const phpRunner = path.join(maintenanceFixture, "php");

  fs.writeFileSync(
    phpRunner,
    `#!/bin/sh
if [ -n "\${FAKE_LOG:-}" ]; then
  printf '%s\\n' "$*" >> "$FAKE_LOG"
fi
case "$1" in
  */composer-2.10.2)
    exit 0
    ;;
esac
if [ "$1" = "artisan" ] && [ "$2" = "down" ]; then
  if [ "\${FAKE_ARTISAN_DOWN:-success}" = "fail" ]; then
    exit 1
  fi
  mkdir -p storage/framework
  cp vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub storage/framework/maintenance.php
  printf '%s\\n' '{"except":["/up"],"template":"errors::503","status":503,"retry":60,"redirect":null,"refresh":null,"secret":null}' > storage/framework/down
  exit 0
fi
if [ "$1" = "-r" ]; then
  node -e 'const fs = require("node:fs"); const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8")); process.exit(Array.isArray(data.except) && data.except.length === 1 && data.except[0] === "/up" && typeof data.template === "string" && data.template !== "" && data.status === 503 && data.retry === 60 && data.redirect === null && (data.refresh ?? null) === null && data.secret === null ? 0 : 1)' "$3"
  exit $?
fi
if [ "$1" = "artisan" ]; then
  exit 0
fi
exit 1
`,
  );
  fs.chmodSync(phpRunner, 0o755);

  const maintenanceFixtureCommand = maintenanceFixtureCommandTemplate.replaceAll(
    "/usr/bin/php8.5",
    phpRunner,
  );
  const runMaintenanceFixture = (
    environment: NodeJS.ProcessEnv = {},
  ): ReturnType<typeof spawnSync> =>
    spawnSync("sh", ["-c", maintenanceFixtureCommand], {
      encoding: "utf8",
      env: {
        ...process.env,
        DEPLOY_DIRECTORY: deployDirectory,
        ...environment,
      },
    });

  const emptyResult = runMaintenanceFixture();

  if (emptyResult.status !== 0) {
    fail("an empty first deployment must skip maintenance mode");
  }

  fs.writeFileSync(path.join(deployDirectory, "artisan"), "");

  const missingVendorResult = runMaintenanceFixture();

  if (missingVendorResult.error) {
    fail(`could not exercise missing-vendor deployment: ${missingVendorResult.error.message}`);
  }

  if (missingVendorResult.status !== 0) {
    fail("an incomplete first deployment without vendor/autoload.php must remain retryable");
  }

  const stub = path.join(
    deployDirectory,
    "vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/maintenance-mode.stub",
  );
  const responder = path.join(deployDirectory, "storage/framework/maintenance.php");

  fs.mkdirSync(path.dirname(stub), { recursive: true });
  fs.mkdirSync(path.dirname(responder), { recursive: true });
  fs.writeFileSync(path.join(deployDirectory, "vendor/autoload.php"), "");
  fs.writeFileSync(stub, "valid responder\n");
  fs.copyFileSync(stub, responder);
  fs.writeFileSync(
    path.join(deployDirectory, "storage/framework/down"),
    JSON.stringify(VALID_MAINTENANCE_PAYLOAD),
  );

  const repairResult = runMaintenanceFixture({
    FAKE_ARTISAN_DOWN: "fail",
  });

  if (repairResult.status !== 0) {
    fail("valid existing maintenance must allow repair when artisan cannot boot");
  }

  const invalidPayloads = [
    { ...VALID_MAINTENANCE_PAYLOAD, except: ["*"] },
    { ...VALID_MAINTENANCE_PAYLOAD, except: undefined },
    { ...VALID_MAINTENANCE_PAYLOAD, except: ["/up", "/admin"] },
    { ...VALID_MAINTENANCE_PAYLOAD, refresh: 0 },
    { ...VALID_MAINTENANCE_PAYLOAD, refresh: "5" },
    {
      ...VALID_MAINTENANCE_PAYLOAD,
      refresh: "0; url=https://example.test",
    },
  ];

  for (const payload of invalidPayloads) {
    fs.writeFileSync(path.join(deployDirectory, "storage/framework/down"), JSON.stringify(payload));

    const invalidPayloadResult = runMaintenanceFixture({
      FAKE_ARTISAN_DOWN: "fail",
    });

    if (invalidPayloadResult.status === 0) {
      fail("unsafe maintenance payload must stop deployment before rsync");
    }
  }

  fs.writeFileSync(
    path.join(deployDirectory, "storage/framework/down"),
    JSON.stringify(VALID_MAINTENANCE_PAYLOAD),
  );

  fs.writeFileSync(responder, "invalid responder\n");

  const invalidMaintenanceResult = runMaintenanceFixture({
    FAKE_ARTISAN_DOWN: "fail",
  });

  if (invalidMaintenanceResult.status === 0) {
    fail("invalid existing maintenance must stop deployment before rsync");
  }

  const postDeployLog = path.join(maintenanceFixture, "post-deploy.log");
  const postDeployFixtureCommand = postDeployFixtureCommandTemplate.replaceAll(
    "/usr/bin/php8.5",
    phpRunner,
  );
  const runPostDeployFixture = (
    environment: NodeJS.ProcessEnv = {},
  ): ReturnType<typeof spawnSync> =>
    spawnSync("sh", ["-c", postDeployFixtureCommand], {
      encoding: "utf8",
      env: {
        ...process.env,
        DEPLOY_DIRECTORY: deployDirectory,
        FAKE_LOG: postDeployLog,
        ...environment,
      },
    });

  const postDeployResult = runPostDeployFixture();

  if (postDeployResult.status !== 0) {
    fail("the repaired release must regenerate maintenance before migrations");
  }

  const postDeployCalls = fs.readFileSync(postDeployLog, "utf8");

  if (
    postDeployCalls.indexOf("composer-2.10.2 install") >= postDeployCalls.indexOf("artisan down") ||
    postDeployCalls.indexOf("artisan down") >= postDeployCalls.indexOf("artisan migrate --force")
  ) {
    fail("maintenance must be regenerated after Composer and before migrations");
  }

  fs.writeFileSync(postDeployLog, "");

  const failedRegenerationResult = runPostDeployFixture({
    FAKE_ARTISAN_DOWN: "fail",
  });

  if (failedRegenerationResult.status === 0) {
    fail("failed maintenance regeneration must stop post-deploy commands");
  }

  const failedRegenerationCalls = fs.readFileSync(postDeployLog, "utf8");

  if (
    failedRegenerationCalls.includes("artisan config:cache") ||
    failedRegenerationCalls.includes("artisan migrate --force")
  ) {
    fail("cache and migration commands must not run after regeneration fails");
  }
} finally {
  fs.rmSync(maintenanceFixture, { recursive: true, force: true });
}

requireText("Deploy to XServer via Rsync", "--delete");
requireText("Run post-deploy commands", "composer-2.10.2 install");
requireText("Run post-deploy commands", "artisan down");
requireText("Run post-deploy commands", "--render='errors::503'");
requireText("Run post-deploy commands", "--retry=60");
requireText("Run post-deploy commands", "storage/framework/down");
requireText("Run post-deploy commands", "storage/framework/maintenance.php");
requireText("Run post-deploy commands", "maintenance-mode.stub");
requireText("Run post-deploy commands", "cmp -s");
requireText("Run post-deploy commands", "json_decode");
requireText("Run post-deploy commands", "artisan migrate --force");

for (const step of ["Enable maintenance mode", "Run post-deploy commands"]) {
  requireText(step, '(\\$data[\\"except\\"] ?? null) === [\\"/up\\"]');
  requireText(step, '(\\$data[\\"refresh\\"] ?? null) === null');
}

requireText("Wait for OPcache revalidation", "sleep 2");
requireText("Disable maintenance mode", "artisan up");
requireText("Verify release health", "for attempt in 1 2 3");
requireText("Verify release health", "--max-time 5");
requireText("Verify release health", "sleep 1");
requireText("Verify release health", '"$status" = "200"');

if (
  steps.at(-2)?.name !== "Disable maintenance mode" ||
  steps.at(-1)?.name !== "Verify release health"
) {
  fail("maintenance lift and release verification must finish deployment");
}

if (
  steps
    .slice(indexOf("Enable maintenance mode") + 1, indexOf("Deploy to XServer via Rsync"))
    .some((step) => step.run?.includes("sleep "))
) {
  fail("deployment must start without a fixed request-drain delay");
}

const artisanDownCount = steps.reduce(
  (count, step) => count + (step.run?.match(/artisan down/g)?.length ?? 0),
  0,
);

if (artisanDownCount !== 2) {
  fail("maintenance must be validated before and after release replacement");
}

if (/\b(?:artisan|php)\b|storage\/framework|maintenance/i.test(runOf("Verify release health"))) {
  fail("release verification must not re-enter maintenance after artisan up");
}

console.log(
  "OK: deployment uses Laravel maintenance, a 2-second OPcache wait, and a bounded /up check.",
);

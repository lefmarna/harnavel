import { spawnSync } from "node:child_process";

import { e2eContainerName } from "./support/artisan";

export default function globalTeardown(): void {
  if (process.env.CI) {
    return;
  }

  const container = e2eContainerName();

  const result = spawnSync("docker", ["rm", "--force", container], {
    encoding: "utf8",
  });

  if (result.status !== 0 && !result.stderr.includes(`No such container: ${container}`)) {
    throw new Error(`E2E server cleanup failed: ${result.stderr || result.stdout}`);
  }
}

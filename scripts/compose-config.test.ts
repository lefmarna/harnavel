import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

interface ComposeConfig {
  services: {
    app: {
      environment?: Record<string, string>;
    };
    nginx: {
      ports: Array<{
        host_ip?: string;
        target: number;
      }>;
    };
  };
}

for (const bindAddress of ["127.0.0.1", "0.0.0.0"]) {
  test(`Nginx の公開アドレス ${bindAddress} を Laravel コンテナへ渡す`, () => {
    const result = spawnSync("docker", ["compose", "config", "--format", "json"], {
      cwd: ROOT,
      encoding: "utf8",
      env: {
        ...process.env,
        DOCKER_BIND_ADDRESS: bindAddress,
      },
    });

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);

    const config = JSON.parse(result.stdout) as ComposeConfig;
    const nginxHttpPort = config.services.nginx.ports.find((port) => port.target === 80);

    assert.equal(nginxHttpPort?.host_ip, bindAddress);
    assert.equal(config.services.app.environment?.DOCKER_BIND_ADDRESS, bindAddress);
  });
}

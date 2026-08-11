#!/usr/bin/env node
/**
 * Claude Code and Codex allow stopping with empty output and block with `decision: "block"`;
 * Antigravity allows it with `{}` and blocks with `decision: "continue"`.
 */
import { spawn } from "node:child_process";
import { readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const scriptPath = resolve(join(__dirname, "stop-verify.mjs"));

const stdinData = readFileSync(0, "utf8");

const child = spawn(process.execPath, [scriptPath], {
  stdio: ["pipe", "pipe", "pipe"],
});

child.stdin.write(stdinData);
child.stdin.end();

let stdout = "";
let stderr = "";
child.stdout.on("data", (chunk) => {
  stdout += chunk;
});
child.stderr.on("data", (chunk) => {
  stderr += chunk;
});

child.on("close", (exitCode) => {
  const raw = stdout.trim();
  const errorOutput = stderr.trim();

  if (!raw && exitCode === 0) {
    console.log(JSON.stringify({}));
    process.exit(0);
  }

  if (exitCode !== 0) {
    const detail =
      [errorOutput, raw && `stdout:\n${raw}`].filter(Boolean).join("\n\n") ||
      `Exited with code ${exitCode}.`;

    console.log(
      JSON.stringify({
        decision: "continue",
        reason: `stop-verify.mjs crashed (exit ${exitCode}). Blocking stop.\n${detail}`,
      }),
    );

    return;
  }

  try {
    const result = JSON.parse(raw);

    if (result.decision === "block") {
      console.log(
        JSON.stringify({
          decision: "continue",
          reason: result.reason ?? "Verification failed.",
        }),
      );
    } else {
      console.log(raw);
    }
  } catch {
    console.log(raw);
  }
});

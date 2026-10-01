import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, "..");

const DOCKERFILE_PATH =
  process.env.TOOLCHAIN_DOCKERFILE ?? path.join(ROOT, ".docker/php/Dockerfile");
const WORKFLOW_PATH =
  process.env.TOOLCHAIN_WORKFLOW ?? path.join(ROOT, ".github/workflows/delivery.yml");
const CIRCLECI_PATH = process.env.TOOLCHAIN_CIRCLECI ?? path.join(ROOT, ".circleci/config.yml");

function matchOne(content: string, re: RegExp, subject: string): string {
  const match = content.match(re);
  const value = match?.[1];

  if (value === undefined) {
    throw new Error(`Could not find ${subject} (pattern: ${re}).`);
  }

  return value;
}

function matchAll(content: string, re: RegExp, subject: string): string[] {
  const values = [...content.matchAll(re)].map((match) => match[1] ?? "");

  if (values.length === 0) {
    throw new Error(`Could not find any ${subject} (pattern: ${re}).`);
  }

  return values;
}

function assertAllEqual(values: string[], expected: string, subject: string, file: string): void {
  const mismatches = values.filter((value) => value !== expected);

  if (mismatches.length === 0) {
    return;
  }

  throw new Error(
    [
      `${subject} mismatch: expected ${expected}, found ${mismatches.join(", ")}.`,
      `Update .docker/php/Dockerfile and ${file} together,`,
      "(docs/infrastructure.md, XServer toolchain).",
    ].join("\n"),
  );
}

function main(): void {
  const dockerfile = fs.readFileSync(DOCKERFILE_PATH, "utf8");
  const workflow = fs.readFileSync(WORKFLOW_PATH, "utf8");

  const phpSeries = matchOne(
    dockerfile,
    /^FROM php:(\d+\.\d+)\.\d+-fpm/m,
    "PHP image series in the Dockerfile",
  );

  const workflowPhpSeries = matchAll(
    workflow,
    /\/usr\/bin\/php(\d+\.\d+)/g,
    "usr/bin/php<major.minor> references in delivery.yml",
  );
  assertAllEqual(workflowPhpSeries, phpSeries, "PHP series", ".github/workflows/delivery.yml");
  console.log(`OK: delivery.yml uses the tested PHP series (${phpSeries}).`);

  // CircleCI の E2E とミューテーションテストも、配備先と同じ系列の PHP で検証する。
  const circleciPhpSeries = matchAll(
    fs.readFileSync(CIRCLECI_PATH, "utf8"),
    /image: cimg\/php:(\d+\.\d+)\./g,
    "cimg/php images in .circleci/config.yml",
  );
  assertAllEqual(circleciPhpSeries, phpSeries, "PHP series", ".circleci/config.yml");
  console.log(`OK: CircleCI tests the deployed PHP series (${phpSeries}).`);
}

main();

import fs from "node:fs";
import path from "node:path";

const ROOT = path.resolve(import.meta.dirname, "..");

function matchAll(file: string, re: RegExp): string[] {
  const content = fs.readFileSync(path.join(ROOT, file), "utf8");
  const values = [...content.matchAll(re)].map((match) => match[1] ?? "");

  if (values.length === 0) {
    throw new Error(`Could not find expected PHP series in ${file} (pattern: ${re}).`);
  }

  return values;
}

function main(): void {
  const [phpSeries] = matchAll(".docker/php/Dockerfile", /^FROM php:(\d+\.\d+)\.\d+-fpm/gm);

  if (phpSeries === undefined) {
    throw new Error("Could not determine PHP series from .docker/php/Dockerfile.");
  }

  const targets: [string, RegExp][] = [
    [".github/workflows/delivery.yml", /\/usr\/bin\/php(\d+\.\d+)/g],
    [".github/workflows/reusable-quality.yml", /^\s+php-version:\s*["']?(\d+\.\d+)["']?\s*$/gm],
    [".github/workflows/mutation.yml", /^\s+php-version:\s*["']?(\d+\.\d+)["']?\s*$/gm],
    [".circleci/config.yml", /image: cimg\/php:(\d+\.\d+)\./g],
  ];

  for (const [file, pattern] of targets) {
    const mismatches = matchAll(file, pattern).filter((value) => value !== phpSeries);

    if (mismatches.length > 0) {
      throw new Error(
        `PHP series mismatch in ${file}: expected ${phpSeries}, found ${mismatches.join(", ")}.`,
      );
    }
  }

  console.log(`OK: Dockerfile, workflows, and CircleCI use PHP ${phpSeries}.`);
}

main();

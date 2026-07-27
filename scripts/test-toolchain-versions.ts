// Verifies that toolchain versions referenced from independent files stay in lockstep:
// - Composer: the image tag in .docker/php/Dockerfile and the versioned
//   ~/opt/bin/composer-<version> paths in .github/workflows/delivery.yml.
// - PHP: the Docker PHP image series (major.minor) and the /usr/bin/php<major.minor>
//   binaries used by delivery.yml remote commands.
//
// The XServer-side composer binary is provisioned manually before the workflow change
// lands. This static check prevents repository references from drifting; delivery.yml's
// preflight separately executes the remote PHAR before maintenance mode and rsync.
// See docs/infrastructure.md (XServer Server Toolchain).
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')

const DOCKERFILE_PATH =
  process.env.TOOLCHAIN_DOCKERFILE ?? path.join(ROOT, '.docker/php/Dockerfile')
const WORKFLOW_PATH =
  process.env.TOOLCHAIN_WORKFLOW ??
  path.join(ROOT, '.github/workflows/delivery.yml')

function matchOne(content: string, re: RegExp, subject: string): string {
  const match = content.match(re)
  const value = match?.[1]

  if (value === undefined) {
    throw new Error(`Could not find ${subject} (pattern: ${re}).`)
  }

  return value
}

function matchAll(content: string, re: RegExp, subject: string): string[] {
  const values = [...content.matchAll(re)].map((match) => match[1] ?? '')

  if (values.length === 0) {
    throw new Error(`Could not find any ${subject} (pattern: ${re}).`)
  }

  return values
}

function assertAllEqual(
  values: string[],
  expected: string,
  subject: string,
): void {
  const mismatches = values.filter((value) => value !== expected)

  if (mismatches.length === 0) {
    return
  }

  throw new Error(
    [
      `${subject} mismatch: expected ${expected}, found ${mismatches.join(', ')}.`,
      'Update .docker/php/Dockerfile and .github/workflows/delivery.yml together,',
      'and provision the matching composer binary on every XServer environment first',
      '(docs/infrastructure.md, XServer Server Toolchain).',
    ].join('\n'),
  )
}

function main(): void {
  const dockerfile = fs.readFileSync(DOCKERFILE_PATH, 'utf8')
  const workflow = fs.readFileSync(WORKFLOW_PATH, 'utf8')

  const phpSeries = matchOne(
    dockerfile,
    /^FROM php:(\d+\.\d+)\.\d+-fpm/m,
    'PHP image series in the Dockerfile',
  )
  const composerVersion = matchOne(
    dockerfile,
    /^COPY --from=composer:(\d+\.\d+\.\d+)/m,
    'Composer image version in the Dockerfile',
  )

  const workflowPhpSeries = matchAll(
    workflow,
    /\/usr\/bin\/php(\d+\.\d+)/g,
    'usr/bin/php<major.minor> references in delivery.yml',
  )
  const workflowComposerVersions = matchAll(
    workflow,
    /composer-(\d+\.\d+\.\d+)/g,
    'composer-<version> references in delivery.yml',
  )
  // The preflight also asserts the PHAR's own `composer --version` output against a literal
  // version, written with escaped dots for grep -E. That literal is not a composer-<version>
  // path, so the check above never sees it: updating the Dockerfile and the PHAR paths while
  // leaving this pattern behind passes every static check and then stops the deployment at the
  // preflight, where the new binary's output fails the old pattern.
  const workflowComposerGrepVersions = [
    ...workflow.matchAll(/Composer version (\d+)\\\.(\d+)\\\.(\d+)/g),
  ].map((match) => `${match[1]}.${match[2]}.${match[3]}`)

  if (workflowComposerGrepVersions.length === 0) {
    throw new Error(
      "Could not find the preflight's `Composer version <version>` match pattern in delivery.yml.",
    )
  }

  assertAllEqual(workflowPhpSeries, phpSeries, 'PHP series')
  assertAllEqual(workflowComposerVersions, composerVersion, 'Composer version')
  assertAllEqual(
    workflowComposerGrepVersions,
    composerVersion,
    'Composer version asserted by the preflight match pattern',
  )

  console.log(
    `OK: delivery.yml matches Dockerfile toolchain (php ${phpSeries}, composer ${composerVersion}).`,
  )
}

main()

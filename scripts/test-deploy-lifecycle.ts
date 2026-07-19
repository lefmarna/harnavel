import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const WORKFLOW_PATH =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ??
  path.join(ROOT, '.github/workflows/delivery.yml')

function stepIndex(workflow: string, name: string): number {
  const index = workflow.indexOf(`- name: ${name}`)

  if (index === -1) {
    throw new Error(`Deployment step was not found: ${name}`)
  }

  return index
}

function stepBlock(workflow: string, name: string): string {
  const start = stepIndex(workflow, name)
  const nextStep = workflow.indexOf('\n      - name:', start + 1)

  return nextStep === -1
    ? workflow.slice(start)
    : workflow.slice(start, nextStep)
}

function assertOrdered(workflow: string, before: string, after: string): void {
  if (stepIndex(workflow, before) < stepIndex(workflow, after)) {
    return
  }

  throw new Error(`Deployment step must run before ${after}: ${before}`)
}

function assertIncludes(
  block: string,
  expected: string,
  subject: string,
): void {
  if (block.includes(expected)) {
    return
  }

  throw new Error(
    `${subject} was not found in the deployment step: ${expected}`,
  )
}

function assertExcludes(
  block: string,
  unexpected: string,
  subject: string,
): void {
  if (!block.includes(unexpected)) {
    return
  }

  throw new Error(`${subject} must not contain: ${unexpected}`)
}

function assertMatches(block: string, expected: RegExp, subject: string): void {
  if (expected.test(block)) {
    return
  }

  throw new Error(`${subject} was not found in the deployment step`)
}

function main(): void {
  const workflow = fs.readFileSync(WORKFLOW_PATH, 'utf8')

  assertOrdered(workflow, 'Setup SSH key', 'Verify XServer toolchain')
  assertOrdered(
    workflow,
    'Verify XServer toolchain',
    'Deploy to XServer via Rsync',
  )

  const preflight = stepBlock(workflow, 'Verify XServer toolchain')

  assertIncludes(
    preflight,
    'test -x /usr/bin/php8.5',
    'XServer PHP executable check',
  )
  assertIncludes(
    preflight,
    'test -r ~/opt/bin/composer-2.10.2',
    'XServer Composer PHAR readability check',
  )
  assertIncludes(
    preflight,
    '/usr/bin/php8.5 ~/opt/bin/composer-2.10.2 --version --no-ansi',
    'XServer Composer execution check',
  )
  assertIncludes(
    preflight,
    "grep -Eq '^Composer version 2\\.10\\.2([[:space:]]|$)'",
    'exact XServer Composer version check',
  )

  assertIncludes(
    workflow,
    'cancel-in-progress: false',
    'non-cancelling deployment concurrency policy',
  )
  assertOrdered(workflow, 'Verify XServer toolchain', 'Enable maintenance mode')
  assertOrdered(workflow, 'Enable maintenance mode', 'Drain in-flight requests')
  assertOrdered(
    workflow,
    'Drain in-flight requests',
    'Deploy to XServer via Rsync',
  )
  assertOrdered(
    workflow,
    'Deploy to XServer via Rsync',
    'Run Post-Deploy Commands',
  )
  assertOrdered(
    workflow,
    'Run Post-Deploy Commands',
    'Disable maintenance mode',
  )

  const enableMaintenance = stepBlock(workflow, 'Enable maintenance mode')
  const drainInFlight = stepBlock(workflow, 'Drain in-flight requests')
  const disableMaintenance = stepBlock(workflow, 'Disable maintenance mode')

  assertMatches(
    drainInFlight,
    /\brun:\s*sleep [1-9]\d*\b/,
    'positive app-calibrated in-flight request drain',
  )

  assertIncludes(
    enableMaintenance,
    '< .github/deploy/enable-maintenance.php',
    'bootstrap-independent maintenance installer',
  )
  assertIncludes(
    enableMaintenance,
    'mkdir -p -- \\"$DEPLOY_DIRECTORY\\"',
    'empty deployment target preparation',
  )
  assertExcludes(
    enableMaintenance,
    'artisan down',
    'maintenance activation that requires the previous application',
  )
  assertIncludes(
    disableMaintenance,
    '/usr/bin/php8.5 artisan up',
    'maintenance recovery command',
  )
  assertIncludes(
    disableMaintenance,
    'rm -f storage/framework/down storage/framework/maintenance.php',
    'driver-independent removal of the standalone responder',
  )
  assertIncludes(
    disableMaintenance,
    'test ! -e storage/framework/down',
    'verification that the down state was removed',
  )
  assertIncludes(
    disableMaintenance,
    'test ! -e storage/framework/maintenance.php',
    'verification that the standalone responder was removed',
  )
  assertExcludes(
    disableMaintenance,
    'if: always()',
    'fail-closed maintenance recovery step',
  )

  console.log(
    'OK: deployment verifies XServer, fails closed in maintenance, and returns live only after post-deploy succeeds.',
  )
}

main()

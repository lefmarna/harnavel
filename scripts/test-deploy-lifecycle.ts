import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import { parse } from 'yaml'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const WORKFLOW =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ??
  path.join(ROOT, '.github/workflows/delivery.yml')

type Step = {
  name?: string
  run?: string
}

type Workflow = {
  concurrency?: {
    'cancel-in-progress'?: boolean
  }
  jobs?: {
    deploy?: {
      steps?: Step[]
    }
  }
}

function fail(message: string): never {
  throw new Error(`Deployment lifecycle check failed: ${message}`)
}

const workflow = parse(fs.readFileSync(WORKFLOW, 'utf8')) as Workflow
const steps = workflow.jobs?.deploy?.steps ?? []

function indexOf(name: string): number {
  const index = steps.findIndex((step) => step.name === name)

  return index === -1 ? fail(`missing step "${name}"`) : index
}

function runOf(name: string): string {
  return steps[indexOf(name)]?.run ?? fail(`step "${name}" has no run script`)
}

function requireText(name: string, expected: string): void {
  if (!runOf(name).includes(expected)) {
    fail(`step "${name}" must contain ${JSON.stringify(expected)}`)
  }
}

function rejectText(expected: string): void {
  const match = steps.find((step) => step.run?.includes(expected))

  if (match) {
    fail(`step "${match.name ?? '(unnamed)'}" must not contain ${expected}`)
  }
}

const ordered = [
  'Validate deployment configuration',
  'Check release-critical files',
  'Setup SSH key',
  'Verify XServer toolchain',
  'Enable maintenance mode',
  'Deploy to XServer via Rsync',
  'Run post-deploy commands',
  'Wait for OPcache revalidation',
  'Disable maintenance mode',
  'Verify release health',
]

for (let index = 1; index < ordered.length; index++) {
  const before = ordered[index - 1] ?? ''
  const after = ordered[index] ?? ''

  if (indexOf(before) >= indexOf(after)) {
    fail(`"${before}" must run before "${after}"`)
  }
}

if (workflow.concurrency?.['cancel-in-progress'] !== false) {
  fail('active deployments must not be cancelled by a newer push')
}

requireText('Enable maintenance mode', 'vendor/autoload.php')
requireText('Enable maintenance mode', 'storage/framework/down')
requireText('Enable maintenance mode', 'storage/framework/maintenance.php')
requireText('Enable maintenance mode', 'maintenance-mode.stub')
requireText('Enable maintenance mode', 'cmp -s')
requireText('Enable maintenance mode', 'json_decode')
requireText('Enable maintenance mode', 'template')
requireText('Enable maintenance mode', 'artisan down')
requireText('Enable maintenance mode', "--render='errors::503'")
requireText('Enable maintenance mode', '--retry=60')

if (
  /\[\s+!\s+-[efrs]\s+[^\]]*storage\/framework\/down/.test(
    runOf('Enable maintenance mode'),
  )
) {
  fail('existing maintenance mode must be refreshed and validated')
}

const maintenanceRun = runOf('Enable maintenance mode')
const remoteStart = maintenanceRun.indexOf('"mkdir -p')
const sshStart = maintenanceRun.lastIndexOf('ssh ', remoteStart)

if (remoteStart === -1 || sshStart === -1) {
  fail('could not locate the remote maintenance command')
}

const maintenanceFixtureCommand = (
  maintenanceRun.slice(0, sshStart) +
  'sh -c ' +
  maintenanceRun.slice(remoteStart)
).replaceAll('sleep "$((attempt * 30))"', ':')
const maintenanceFixture = fs.mkdtempSync(
  path.join(os.tmpdir(), 'deploy-lifecycle-'),
)

try {
  const deployDirectory = path.join(maintenanceFixture, 'deploy')

  const runMaintenanceFixture = () =>
    spawnSync('sh', ['-c', maintenanceFixtureCommand], {
      encoding: 'utf8',
      env: {
        ...process.env,
        DEPLOY_DIRECTORY: deployDirectory,
      },
    })

  const emptyResult = runMaintenanceFixture()

  if (emptyResult.status !== 0) {
    fail('an empty first deployment must skip maintenance mode')
  }

  fs.writeFileSync(path.join(deployDirectory, 'artisan'), '')

  const missingVendorResult = runMaintenanceFixture()

  if (missingVendorResult.error) {
    fail(
      `could not exercise missing-vendor deployment: ${missingVendorResult.error.message}`,
    )
  }

  if (missingVendorResult.status === 0) {
    fail('an existing tree without vendor/autoload.php must stop deployment')
  }
} finally {
  fs.rmSync(maintenanceFixture, { recursive: true, force: true })
}

requireText('Deploy to XServer via Rsync', '--delete')
requireText('Run post-deploy commands', 'artisan migrate --force')
requireText('Wait for OPcache revalidation', 'sleep 2')
requireText('Disable maintenance mode', 'artisan up')
requireText('Verify release health', 'for attempt in 1 2 3')
requireText('Verify release health', '--max-time 5')
requireText('Verify release health', 'sleep 1')
requireText('Verify release health', '"$status" = "200"')

for (const removed of [
  'Drain in-flight requests',
  'Mark maintenance lift attempt',
  'Withdraw unhealthy release',
]) {
  if (steps.some((step) => step.name === removed)) {
    fail(`obsolete step remains: "${removed}"`)
  }
}

rejectText('enable-maintenance.php')
rejectText('verify-release-health.mjs')

console.log(
  'OK: deployment uses Laravel maintenance, a 2-second OPcache wait, and a bounded /up check.',
)

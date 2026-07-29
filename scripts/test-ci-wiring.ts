import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import { parse } from 'yaml'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const ACTION_REF = './.github/actions/ci-lint'
const TEST_REF = './.github/workflows/reusable-test.yml'

type Step = {
  run?: string
  uses?: string
}

type Job = {
  needs?: string | string[]
  steps?: Step[]
  uses?: string
}

type Workflow = {
  jobs?: Record<string, Job>
  runs?: {
    steps?: Step[]
  }
}

function load(relativePath: string): Workflow {
  return (parse(fs.readFileSync(path.join(ROOT, relativePath), 'utf8')) ??
    {}) as Workflow
}

function fail(message: string): never {
  throw new Error(`CI wiring check failed: ${message}`)
}

function usesAction(job: Job | undefined): boolean {
  return job?.steps?.some((step) => step.uses === ACTION_REF) ?? false
}

function needs(job: Job | undefined, dependency: string): boolean {
  const dependencies = job?.needs

  return Array.isArray(dependencies)
    ? dependencies.includes(dependency)
    : dependencies === dependency
}

const action = load('.github/actions/ci-lint/action.yml')
const actionCommands = (action.runs?.steps ?? [])
  .map((step) => step.run ?? '')
  .join('\n')

for (const command of [
  'vendor/bin/pint --test',
  'composer analyse',
  'pnpm run format:check',
  'pnpm run lint:check',
  'pnpm run types:check',
  'pnpm run lint:docs',
  'pnpm run test:unit',
  'pnpm run test:deploy-lifecycle',
  'pnpm run test:deploy-rsync-excludes',
  'pnpm run test:toolchain-versions',
]) {
  if (!actionCommands.includes(command)) {
    fail(`ci-lint must run ${JSON.stringify(command)}`)
  }
}

const check = load('.github/workflows/check.yml').jobs

if (!usesAction(check?.check)) {
  fail('check.yml must use ci-lint')
}

const quality = load('.github/workflows/quality.yml').jobs

if (!usesAction(quality?.quality)) {
  fail('quality.yml must use ci-lint')
}

if (quality?.test?.uses !== TEST_REF || !needs(quality.test, 'quality')) {
  fail('quality.yml test must use reusable-test after ci-lint')
}

const delivery = load('.github/workflows/delivery.yml').jobs

if (!usesAction(delivery?.lint)) {
  fail('delivery.yml must use ci-lint')
}

if (
  delivery?.test?.uses !== TEST_REF ||
  !needs(delivery.test, 'lint') ||
  !needs(delivery?.deploy, 'test')
) {
  fail('delivery must run reusable-test after ci-lint and before deploy')
}

console.log('OK: required checks gate pull requests and deployments.')

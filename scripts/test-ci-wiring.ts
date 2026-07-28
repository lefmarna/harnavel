// Shared checks run once in ci-lint, and delivery's deploy job depends on them.
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

import { parse } from 'yaml'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const WORKFLOWS_DIR = process.env.CI_WIRING_WORKFLOWS_DIR ?? '.github/workflows'
const ACTION_PATH =
  process.env.CI_WIRING_ACTION ?? '.github/actions/ci-lint/action.yml'
const ACTION_REF = './.github/actions/ci-lint'

// `pnpm test:e2e` は DB サービスとビルド済みアセットが要るため reusable-test.yml 側。
const SHARED_CHECKS = [
  'format:check',
  'lint:check',
  'types:check',
  'lint:docs',
  'test:unit',
  'test:deploy-lifecycle',
  'test:deploy-rsync-excludes',
  'test:release-health',
  'test:toolchain-versions',
  'test:ci-wiring',
]

const GATE_WORKFLOWS = ['check.yml', 'quality.yml', 'delivery.yml']

type Step = { run?: string; uses?: string }
type Job = { needs?: string | string[]; steps?: Step[]; uses?: string }

const failures: string[] = []

function load(filePath: string): {
  runs?: { steps?: Step[] }
  jobs?: Record<string, Job>
} {
  return parse(fs.readFileSync(path.resolve(ROOT, filePath), 'utf8')) ?? {}
}

const scriptsRun = (steps: Step[] = []): string[] =>
  steps.flatMap((step) =>
    [...(step.run ?? '').matchAll(/pnpm run ([\w:-]+)/g)].map(
      ([, name = '']) => name,
    ),
  )

const usesSharedAction = (job: Job): boolean =>
  (job.steps ?? []).some((step) => step.uses === ACTION_REF)

function assertSharedActionRunsEveryCheckOnce(): void {
  const scripts = scriptsRun(load(ACTION_PATH).runs?.steps)

  for (const check of SHARED_CHECKS) {
    const count = scripts.filter((name) => name === check).length

    if (count !== 1) {
      failures.push(
        `${ACTION_PATH} must run \`pnpm run ${check}\` exactly once (found ${count}).`,
      )
    }
  }
}

function assertNoWorkflowDuplicatesASharedCheck(): void {
  for (const file of fs.readdirSync(path.resolve(ROOT, WORKFLOWS_DIR))) {
    if (!/\.ya?ml$/.test(file)) {
      continue
    }

    const jobs = Object.values(load(path.join(WORKFLOWS_DIR, file)).jobs ?? {})
    const scripts = jobs.flatMap((job) => scriptsRun(job.steps))

    for (const check of SHARED_CHECKS.filter((name) =>
      scripts.includes(name),
    )) {
      failures.push(
        `${file} runs \`pnpm run ${check}\` inline; it belongs to ${ACTION_PATH} only, or the two copies drift.`,
      )
    }
  }
}

function assertGatesUseTheSharedAction(): void {
  for (const file of GATE_WORKFLOWS) {
    const jobs = Object.values(load(path.join(WORKFLOWS_DIR, file)).jobs ?? {})

    if (!jobs.some(usesSharedAction)) {
      failures.push(
        `${file} must use \`${ACTION_REF}\`; otherwise it gates nothing.`,
      )
    }
  }
}

/**
 * uses: だけでは deploy を止められない。並走する lint ジョブは失敗を報告するだけなので、
 * ci-lint を走らせるジョブが deploy の needs 連鎖に入っていることを要求する。
 */
function assertDeployDependsOnTheSharedAction(): void {
  const jobs = load(path.join(WORKFLOWS_DIR, 'delivery.yml')).jobs ?? {}
  const deploy = jobs.deploy

  if (deploy === undefined) {
    failures.push('delivery.yml must keep a "deploy" job.')

    return
  }

  const needsOf = (job: Job): string[] => [job.needs ?? []].flat()
  const pending = needsOf(deploy)
  const visited = new Set<string>()

  while (pending.length > 0) {
    const name = pending.shift() ?? ''

    if (visited.has(name)) {
      continue
    }

    visited.add(name)

    const job = jobs[name]

    if (job === undefined) {
      failures.push(
        `delivery.yml: "deploy" needs "${name}", which is not defined here.`,
      )

      continue
    }

    if (usesSharedAction(job)) {
      return
    }

    pending.push(...needsOf(job))
  }

  failures.push(
    `delivery.yml: no job in "deploy"'s needs chain uses ${ACTION_REF}, so the shared checks cannot block a deployment.`,
  )
}

assertSharedActionRunsEveryCheckOnce()
assertNoWorkflowDuplicatesASharedCheck()
assertGatesUseTheSharedAction()
assertDeployDependsOnTheSharedAction()

if (failures.length > 0) {
  throw new Error(`CI wiring check failed:\n- ${failures.join('\n- ')}`)
}

console.log(
  `OK: ${SHARED_CHECKS.length} shared checks run once in ${ACTION_PATH}, and delivery's deploy job depends on them.`,
)

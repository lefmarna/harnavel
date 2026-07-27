import { spawnSync } from 'node:child_process'
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

function stepRunScript(workflow: string, name: string): string {
  const block = stepBlock(workflow, name)
  const marker = '\n        run: |\n'
  const start = block.indexOf(marker)

  if (start === -1) {
    throw new Error(`Deployment step has no literal run block: ${name}`)
  }

  const indent = ' '.repeat(10)
  const lines: string[] = []

  for (const line of block.slice(start + marker.length).split('\n')) {
    if (line.trim() === '') {
      lines.push('')
      continue
    }

    if (!line.startsWith(indent)) {
      break
    }

    lines.push(line.slice(indent.length))
  }

  return lines.join('\n')
}

// 抽出した run block はランナーの書き込み先ファイルを継承してはならない。CI 上で
// このテストを走らせると、テスト用の値が lint ジョブの実サマリへ
// 追記され、サマリを確認する運用者を誤認させる。
const RUNNER_WRITE_TARGETS = {
  GITHUB_STEP_SUMMARY: '',
  GITHUB_ENV: '',
  GITHUB_OUTPUT: '',
  GITHUB_PATH: '',
} as const

// GitHub Actions と同じ bash -e で実際に実行し、終了コードで許可 / 拒否を判定する。
// inherited は「ランナーから継承される環境」を再現するための注入口。RUNNER_WRITE_TARGETS が
// 継承値を実際に打ち消していることを検証するために使う（既定は本物の process.env）。
function runShellStep(
  script: string,
  env: Record<string, string>,
  inherited: NodeJS.ProcessEnv = process.env,
): number {
  const result = spawnSync('bash', ['-e', '-c', script], {
    env: { ...inherited, ...RUNNER_WRITE_TARGETS, ...env },
    encoding: 'utf8',
  })

  if (result.error !== undefined) {
    throw result.error
  }

  return result.status ?? 1
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

  // 版番号そのものは Dockerfile から導出して test-toolchain-versions.ts が照合する。
  // ここで固定するのは preflight の作り方のほう: 存在確認だけで済ませず PHAR を実際に
  // 実行し、前方一致ではなく厳密一致で版を確かめる（前方一致は 2.10.20 を通す）。
  assertMatches(
    preflight,
    /test -x \/usr\/bin\/php\d+\.\d+/,
    'XServer PHP executable check',
  )
  assertMatches(
    preflight,
    /test -r ~\/opt\/bin\/composer-\d+\.\d+\.\d+/,
    'XServer Composer PHAR readability check',
  )
  assertMatches(
    preflight,
    /\/usr\/bin\/php\d+\.\d+ ~\/opt\/bin\/composer-\d+\.\d+\.\d+ --version --no-ansi/,
    'XServer Composer execution check',
  )
  assertMatches(
    preflight,
    /grep -Eq '\^Composer version [0-9\\.]+\(\[\[:space:\]\]\|\$\)'/,
    'exact XServer Composer version match, not a prefix',
  )

  assertIncludes(
    workflow,
    'cancel-in-progress: false',
    'non-cancelling deployment concurrency policy',
  )
  assertOrdered(
    workflow,
    'Validate deployment configuration',
    'Enable maintenance mode',
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

  assertOrdered(
    workflow,
    'Run Post-Deploy Commands',
    'Mark maintenance lift attempt',
  )
  assertOrdered(
    workflow,
    'Mark maintenance lift attempt',
    'Disable maintenance mode',
  )
  assertOrdered(workflow, 'Disable maintenance mode', 'Verify release health')
  assertOrdered(workflow, 'Verify release health', 'Withdraw unhealthy release')

  const liftAttempt = stepBlock(workflow, 'Mark maintenance lift attempt')
  const verifyHealth = stepBlock(workflow, 'Verify release health')
  const withdrawRelease = stepBlock(workflow, 'Withdraw unhealthy release')

  // 検証文字列の固定ではなく、Validate ステップの script を実際に実行して許可 / 拒否を
  // 確認する。curl が拒否する値（末尾改行・空白・範囲外ポート等）が Validate を通過すると、
  // サーバー更新と maintenance 解除の後の health check で初めて落ちて正常なリリースを
  // 503 へ引き戻す＝「不正値はリモートへ触れる前に fail する」契約が破れる。
  const validateScript = stepRunScript(
    workflow,
    'Validate deployment configuration',
  )
  const acceptedHealthcheckUrls = [
    'https://example.com/up',
    'https://sub.example.co.jp:8443/up',
  ]
  const rejectedHealthcheckUrls = [
    '',
    'example.com/up',
    // 到達性プローブが PHP を通らない URL。静的ファイルやオリジン直下を指すと、
    // アプリが起動できない状態でもアセットと合わせて 200 が揃い、壊れたリリースを
    // 公開したまま検証が通ってしまう。末尾一致では防げないので完全一致で弾く。
    'https://example.com',
    'https://example.com/manifest.webmanifest',
    'https://example.com/up/',
    'https://example.com/static/up',
    'https://example.com/health/up',
    'https:///up',
    'http://',
    // .htaccess / .htaccess.develop は HTTPS を無条件に 301 する。curl は追従しないため、
    // http URL は健全なリリースでも必ず検証に失敗し withdraw を招く。
    'http://example.com/up',
    'https://user@example.com/up',
    'https://example.com/up?probe=1',
    'https://example.com#probe',
    'https://example.com:99999/up',
    'https://example.com/up up',
    'https://example.com/up\n',
    'junk\nhttps://example.com/up',
    ' https://example.com/up',
  ]

  for (const url of acceptedHealthcheckUrls) {
    if (
      runShellStep(validateScript, {
        HEALTHCHECK_URL: url,
      }) !== 0
    ) {
      throw new Error(
        `Validate deployment configuration must accept ${JSON.stringify(url)}`,
      )
    }
  }

  for (const url of rejectedHealthcheckUrls) {
    if (
      runShellStep(validateScript, {
        HEALTHCHECK_URL: url,
      }) === 0
    ) {
      throw new Error(
        `Validate deployment configuration must reject ${JSON.stringify(url)}`,
      )
    }
  }

  // Withdraw の if 条件は step outcome を参照する。id が欠けると参照が空に評価され、
  // 成功時にも withdraw が走る（または全く走らない）ため、配線ごと固定する。
  assertIncludes(
    liftAttempt,
    'id: maintenance-lift-attempt',
    'step id referenced by the withdrawal condition',
  )
  assertExcludes(
    liftAttempt,
    'if:',
    'unconditional maintenance lift attempt marker',
  )
  assertIncludes(
    verifyHealth,
    'id: verify-release-health',
    'step id referenced by the withdrawal condition',
  )
  // 判定ロジックは scripts/verify-release-health.mjs にあり、その振る舞い（2プローブ・
  // 再検証窓の settle・リダイレクト非追従・30秒タイムアウト・リトライ）は実 CLI を通す
  // test-release-health.ts が固定する。ここは配線だけを見る。
  assertIncludes(
    verifyHealth,
    'run: node scripts/verify-release-health.mjs',
    'release health verification delegated to the dependency-free script',
  )

  if (!fs.existsSync(path.join(ROOT, 'scripts/verify-release-health.mjs'))) {
    throw new Error(
      'The release health verification step points at a script that does not exist',
    )
  }

  // deploy ジョブは pnpm install も setup-node もしないため、ランナー同梱コマンド以外を
  // 増やさない。curl / jq へ戻すと、この 1 行に集約した HTTP 契約が再び YAML へ散る。
  for (const command of ['curl ', 'jq ', 'npx ', 'pnpm ', 'tsx ']) {
    assertExcludes(
      verifyHealth,
      command,
      'release health verification kept free of extra runner dependencies',
    )
  }

  assertExcludes(
    verifyHealth,
    'if: always()',
    'release health verification step',
  )
  assertIncludes(
    withdrawRelease,
    'always()',
    'withdrawal that also covers manual cancellation during verification',
  )
  // 解除ステップの outcome == 'success' を条件にしてはならない: リモートで artisan up が
  // 完了した直後の SSH 切断・キャンセルでは、サイトが公開済みでも outcome が
  // failure / cancelled になり、未検証リリースが取り残される。
  assertIncludes(
    withdrawRelease,
    "steps.maintenance-lift-attempt.outcome == 'success'",
    'withdrawal gated on the recorded lift attempt',
  )
  assertExcludes(
    withdrawRelease,
    "steps.disable-maintenance.outcome == 'success'",
    'withdrawal condition that misses a partially successful lift',
  )
  assertIncludes(
    withdrawRelease,
    "steps.verify-release-health.outcome != 'success'",
    'withdrawal whenever verification did not succeed',
  )
  assertIncludes(
    withdrawRelease,
    '< .github/deploy/enable-maintenance.php',
    'fail-closed withdrawal of an unhealthy release',
  )

  console.log(
    'OK: deployment verifies XServer, fails closed in maintenance, returns live only after post-deploy succeeds, and withdraws unhealthy releases.',
  )
}

main()

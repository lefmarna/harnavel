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

// GitHub Actions と同じ bash -e で実際に実行し、終了コードで許可 / 拒否を判定する。
function runShellStep(script: string, env: Record<string, string>): number {
  const result = spawnSync('bash', ['-e', '-c', script], {
    env: { ...process.env, ...env },
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
  // リリースIDは配布物へ焼き込んでから転送する。転送後に焼き込むと、検証が一致を見ても
  // その ID を持つコードがサーバーにあるとは限らない。
  assertOrdered(workflow, 'Create deployment package', 'Stamp release identity')
  assertOrdered(
    workflow,
    'Stamp release identity',
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
    'https://example.com',
    'https://sub.example.co.jp:8443/health/up',
    // WHATWG canonical はパスに [ ] を raw のまま含み得る。Validate が受理する以上、
    // curl 側は --globoff でリテラルに受理させて許可集合を一致させる（下の curl 検査）。
    'https://example.com/health/[canary]',
  ]
  const rejectedHealthcheckUrls = [
    '',
    'example.com/up',
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

  // リリース同一性の期待値も同じステップで検証する。網羅的なケースは
  // scripts/test-release-identity.ts が持つので、ここでは URL 判定の前提だけ満たす。
  const validReleaseEnv = {
    RELEASE_ID: `${'a'.repeat(40)}-4242-1`,
    RELEASE_IDENTITY_ENFORCEMENT: 'enforce',
  }

  for (const url of acceptedHealthcheckUrls) {
    if (
      runShellStep(validateScript, {
        ...validReleaseEnv,
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
        ...validReleaseEnv,
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
  assertIncludes(
    verifyHealth,
    "sed -E 's#^(https?://[^/?#]+).*$#\\1#'",
    'origin extraction that stops before path, query, and fragment',
  )
  assertIncludes(
    verifyHealth,
    "-w '%{http_code}'",
    'HTTP status observation of the released application',
  )
  assertIncludes(
    verifyHealth,
    '"$app_status" = "200"',
    'strict 200 requirement for the released application',
  )
  assertIncludes(
    verifyHealth,
    '.deploy/public/build/manifest.json',
    'manifest-driven selection of a deployed built asset',
  )
  assertIncludes(
    verifyHealth,
    '"$asset_status" = "200"',
    'strict 200 requirement for a deployed built asset',
  )
  // 到達性とアセット存在だけでは「今回のリリースが応答した」ことを示せない。PHP 経由の
  // 応答が期待するデプロイ試行IDを返すことまで求める（振る舞いは test-release-identity.ts）。
  assertIncludes(
    verifyHealth,
    '"$observed_release" = "$RELEASE_ID"',
    'release identity requirement for the deployed application',
  )
  assertIncludes(
    verifyHealth,
    'release_url="${origin}/up/release"',
    'PHP-served release identity probe',
  )

  const stampReleaseIdentity = stepBlock(workflow, 'Stamp release identity')

  assertIncludes(
    stampReleaseIdentity,
    'const file = ".deploy/public/index.php"',
    'release identity stamped into the front controller every request executes',
  )
  assertExcludes(
    stampReleaseIdentity,
    'bootstrap/release',
    'release identity written to a file that stale bytecode could still read',
  )
  // curl は既定で [ ] { } を URL glob と解釈し、Validate が受理した canonical URL を
  // 拒否・複数 URL へ展開し得る。検証ステップの curl はすべてリテラル URL 動作に固定する。
  const curlInvocations = verifyHealth
    .split('\n')
    .filter((line) => line.includes('$(curl '))

  if (curlInvocations.length < 3) {
    throw new Error(
      'Release health verification must curl the health endpoint, a built asset, and the release identity endpoint',
    )
  }

  for (const line of curlInvocations) {
    assertIncludes(
      line,
      '--globoff',
      'literal-URL curl matching the validated accept set',
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

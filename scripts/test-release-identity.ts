import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import fs from 'node:fs'
import http from 'node:http'
import type { AddressInfo } from 'node:net'
import os from 'node:os'
import path from 'node:path'
import { after, before, describe, it } from 'node:test'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const WORKFLOW_PATH =
  process.env.DEPLOY_LIFECYCLE_WORKFLOW ??
  path.join(ROOT, '.github/workflows/delivery.yml')
const FRONT_CONTROLLER_PATH = path.join(ROOT, 'public/index.php')
const RELEASE_VERIFICATION_PATH = path.join(
  ROOT,
  'app/Support/ReleaseVerification.php',
)
const VERIFY_SCRIPT_PATH = path.join(ROOT, 'scripts/verify-release-health.mjs')
const OPCACHE_REVALIDATION_PATH = path.join(
  ROOT,
  'app/Support/OpcacheRevalidation.php',
)
const PLACEHOLDER = "define('LARAVEL_DEPLOYMENT_RELEASE_ID', '');"
const VALID_RELEASE_ID = `${'a'.repeat(40)}-4242-1`
const ASSET_FILE = 'assets/app-DEADBEEF.js'

const workflow = fs.readFileSync(WORKFLOW_PATH, 'utf8')

function stepRunScript(name: string): string {
  const start = workflow.indexOf(`- name: ${name}`)

  if (start === -1) {
    throw new Error(`Deployment step was not found: ${name}`)
  }

  const nextStep = workflow.indexOf('\n      - name:', start + 1)
  const block =
    nextStep === -1 ? workflow.slice(start) : workflow.slice(start, nextStep)
  const marker = '\n        run: |\n'
  const runStart = block.indexOf(marker)

  if (runStart === -1) {
    throw new Error(`Deployment step has no literal run block: ${name}`)
  }

  const indent = ' '.repeat(10)
  const lines: string[] = []

  for (const line of block.slice(runStart + marker.length).split('\n')) {
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

interface ShellResult {
  status: number
  stderr: string
  stdout: string
}

// 抽出した run block はランナーの書き込み先ファイルを継承してはならない。CI 上で
// このテストを走らせると、テスト用の enforcement 値が lint ジョブの実サマリへ
// 追記され、サマリで適用段階を確認する運用者を誤認させる。
const RUNNER_WRITE_TARGETS = {
  GITHUB_STEP_SUMMARY: '',
  GITHUB_ENV: '',
  GITHUB_OUTPUT: '',
  GITHUB_PATH: '',
} as const

/**
 * GitHub Actions と同じ bash -e で実行し、終了コードとログを検証する。
 * スタブ HTTP サーバは同じプロセスで動くため、同期実行するとイベントループが止まり
 * curl が応答を受け取れない。必ず非同期で待つ。
 */
function runShellStep(
  script: string,
  env: Record<string, string>,
  cwd: string = ROOT,
  // 「ランナーから継承される環境」の注入口。RUNNER_WRITE_TARGETS が継承値を実際に
  // 打ち消していることを検証するために使う（既定は本物の process.env）。
  inherited: NodeJS.ProcessEnv = process.env,
): Promise<ShellResult> {
  return new Promise((resolve, reject) => {
    const child = spawn('bash', ['-e', '-c', script], {
      cwd,
      env: { ...inherited, ...RUNNER_WRITE_TARGETS, ...env },
    })
    let stdout = ''
    let stderr = ''

    child.stdout.setEncoding('utf8')
    child.stderr.setEncoding('utf8')
    child.stdout.on('data', (chunk: string) => (stdout += chunk))
    child.stderr.on('data', (chunk: string) => (stderr += chunk))
    child.on('error', reject)
    child.on('close', (code) => resolve({ status: code ?? 1, stderr, stdout }))
  })
}

function attemptCount(result: ShellResult): number {
  return [...result.stderr.matchAll(/health check attempt (\d+):/g)].length
}

interface OpcacheSnapshot {
  enabled: boolean | null
  extensionLoaded: boolean
  preloadConfigured: boolean | null
  revalidateFrequency: number | null
  validateTimestamps: boolean | null
}

const SAFE_OPCACHE: OpcacheSnapshot = {
  enabled: true,
  extensionLoaded: true,
  preloadConfigured: false,
  revalidateFrequency: 0,
  validateTimestamps: true,
}

function snapshot(overrides: Partial<OpcacheSnapshot> = {}): OpcacheSnapshot {
  return { ...SAFE_OPCACHE, ...overrides }
}

interface ReleaseResponse {
  body?: string
  opcache?: OpcacheSnapshot
  release?: string | null
  status: number
}

function releaseBody(response: ReleaseResponse): string {
  return (
    response.body ??
    JSON.stringify({
      opcache: response.opcache ?? SAFE_OPCACHE,
      release: response.release ?? null,
    })
  )
}

interface StubOptions {
  /** 単一値のほか、試行順に消費する系列も渡せる（尽きたら最後の値を繰り返す）。 */
  appStatus?: number | number[]
  assetStatus?: number | number[]
  /** 応答は試行順に消費し、尽きたら最後の応答を繰り返す。 */
  releaseResponses: ReleaseResponse[]
}

function statusAt(
  status: number | number[] | undefined,
  index: number,
): number {
  if (status === undefined) {
    return 200
  }

  if (!Array.isArray(status)) {
    return status
  }

  return status[Math.min(index, status.length - 1)] ?? 200
}

interface Stub {
  close: () => Promise<void>
  origin: string
  releaseRequestHeaders: http.IncomingHttpHeaders[]
}

async function startStub(options: StubOptions): Promise<Stub> {
  const releaseRequestHeaders: http.IncomingHttpHeaders[] = []
  let appRequests = 0
  let assetRequests = 0
  const server = http.createServer((request, response) => {
    const url = request.url ?? '/'

    if (url === '/up/release') {
      const index = Math.min(
        releaseRequestHeaders.length,
        options.releaseResponses.length - 1,
      )
      const releaseResponse = options.releaseResponses[index]

      releaseRequestHeaders.push(request.headers)

      if (releaseResponse === undefined) {
        response.writeHead(500).end()

        return
      }

      response
        .writeHead(releaseResponse.status, {
          'Content-Type': 'application/json',
        })
        .end(releaseBody(releaseResponse))

      return
    }

    if (url.startsWith('/build/')) {
      response
        .writeHead(statusAt(options.assetStatus, assetRequests))
        .end('asset')
      assetRequests += 1

      return
    }

    response.writeHead(statusAt(options.appStatus, appRequests)).end('up')
    appRequests += 1
  })

  await new Promise<void>((resolve) => {
    server.listen(0, '127.0.0.1', resolve)
  })

  const address = server.address() as AddressInfo

  return {
    close: () =>
      new Promise<void>((resolve, reject) => {
        // curl は keep-alive で接続を残す。破棄しないと close がタイムアウトまで待たされる。
        server.closeAllConnections()
        server.close((error) => (error ? reject(error) : resolve()))
      }),
    origin: `http://127.0.0.1:${address.port}`,
    releaseRequestHeaders,
  }
}

/**
 * リトライ待機を実測で縮める。実時間は進めたいので 0 にはしない: settle window の検証は
 * 経過秒数に依存するため、待たない置き換えでは「経過後に成功する」経路を通せない。
 */
let verifyWorkspace = ''

before(() => {
  verifyWorkspace = fs.mkdtempSync(
    path.join(os.tmpdir(), 'release-identity-deploy-'),
  )

  const manifestDirectory = path.join(verifyWorkspace, '.deploy/public/build')

  fs.mkdirSync(manifestDirectory, { recursive: true })
  fs.writeFileSync(
    path.join(manifestDirectory, 'manifest.json'),
    JSON.stringify({
      'resources/js/app.tsx': { file: ASSET_FILE, isEntry: true },
    }),
  )
})

after(() => {
  fs.rmSync(verifyWorkspace, { force: true, recursive: true })
})

interface VerifyOptions extends StubOptions {
  enforcement?: string
  releaseId?: string
}

async function verifyReleaseHealth(
  options: VerifyOptions,
): Promise<
  ShellResult & { releaseRequestHeaders: http.IncomingHttpHeaders[] }
> {
  const stub = await startStub(options)

  try {
    // ワークフローから抽出した shell ではなく、delivery.yml が実際に呼ぶ CLI をそのまま
    // 実行する。以下のケース群は「curl 実装から fetch 実装へ移しても HTTP 契約が変わって
    // いない」ことの検証でもあるので、モジュール直呼びではなくプロセス越しに回す。
    const result = await runShellStep(
      `node ${JSON.stringify(VERIFY_SCRIPT_PATH)}`,
      {
        HEALTHCHECK_URL: `${stub.origin}/up`,
        RELEASE_HEALTH_RETRY_MS: '250',
        RELEASE_ID: options.releaseId ?? VALID_RELEASE_ID,
        RELEASE_IDENTITY_ENFORCEMENT: options.enforcement ?? 'enforce',
      },
      verifyWorkspace,
    )

    return { ...result, releaseRequestHeaders: stub.releaseRequestHeaders }
  } finally {
    await stub.close()
  }
}

describe('リリース健全性検証', () => {
  it('アプリ200・アセット200でもリリースIDが不一致なら成功終了しない', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.notEqual(result.status, 0)
    assert.equal(attemptCount(result), 10)
  })

  it('不一致が続いた後に期待IDが観測されれば成功する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        { release: 'previous-release', status: 200 },
        { release: 'previous-release', status: 200 },
        { release: VALID_RELEASE_ID, status: 200 },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 2)
  })

  it('最後まで不一致なら終了コード1で失敗し期待IDと最終観測IDを出力する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.equal(result.status, 1)
    assert.match(
      result.stderr,
      new RegExp(
        `expected ${VALID_RELEASE_ID}, last observed previous-release`,
      ),
    )
  })

  it('フロントエンド無変更で旧リリースと同名アセットが200でもリリースID不一致を検出する', async () => {
    // manifest が旧リリースと同じハッシュ名を指していてもアセットは 200 を返す。
    const result = await verifyReleaseHealth({
      assetStatus: 200,
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.notEqual(result.status, 0)
    assert.match(result.stderr, /asset=200/)
  })

  it('リリースエンドポイントが404や非JSONを返す間は成功せずリトライを継続する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        { body: '<!doctype html><title>Not Found</title>', status: 404 },
        { body: 'not json', status: 200 },
        { release: VALID_RELEASE_ID, status: 200 },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stderr, /release=404\/none/)
    assert.match(result.stderr, /opcache=unobserved/)
  })

  it('同一commit SHAの再デプロイでは前回試行のIDを成功として扱わない', async () => {
    const commit = 'b'.repeat(40)
    const result = await verifyReleaseHealth({
      releaseId: `${commit}-4242-2`,
      releaseResponses: [{ release: `${commit}-4242-1`, status: 200 }],
    })

    assert.notEqual(result.status, 0)
  })

  it('リリースエンドポイントが503を返す場合はID一致でも成功しない', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [{ release: VALID_RELEASE_ID, status: 503 }],
    })

    assert.notEqual(result.status, 0)
  })

  it('期待IDが空ならリモートが空IDを返しても成功しない', async () => {
    const result = await verifyReleaseHealth({
      releaseId: '',
      releaseResponses: [{ release: '', status: 200 }],
    })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /RELEASE_ID is required/)
  })

  it('列挙外の適用モードならリモート応答を待たずに失敗する', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'soft',
      releaseResponses: [{ release: VALID_RELEASE_ID, status: 200 }],
    })

    assert.equal(result.status, 1)
    assert.equal(result.releaseRequestHeaders.length, 0)
  })

  it('リリースエンドポイントへのリクエストがキャッシュ無効化ヘッダーを送る', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [{ release: VALID_RELEASE_ID, status: 200 }],
    })

    assert.equal(result.releaseRequestHeaders[0]?.['cache-control'], 'no-cache')
    assert.equal(result.releaseRequestHeaders[0]?.pragma, 'no-cache')
  })
})

describe('OPcacheゲート（enforce）', () => {
  it('タイムスタンプ再検証が無効ならID一致でも即座に失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ validateTimestamps: false }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.equal(attemptCount(result), 0)
    assert.match(result.stderr, /::error::the web runtime cannot guarantee/)
  })

  it('preloadが設定済みならID一致でも即座に失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ preloadConfigured: true }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.equal(attemptCount(result), 0)
  })

  it('再検証間隔が上限を超える61ならID一致でも即座に失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 61 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.equal(attemptCount(result), 0)
  })

  it('再検証間隔が上限ちょうどの60なら即座に失敗せずリトライを継続する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 60 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.doesNotMatch(
      result.stderr,
      /::error::the web runtime cannot guarantee/,
    )
    assert.equal(attemptCount(result), 10)
    assert.match(result.stderr, /settle=\d+s\/60s/)
  })

  it('ini値を解釈できない場合は即座に失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ enabled: null }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.equal(attemptCount(result), 0)
    assert.match(result.stderr, /invalid/)
  })

  // 比較対象は期待IDを返した応答同士。settle 待ちの間に別の実行単位が別の設定を返したら、
  // 環境が均一であることを証明できない。
  it('期待IDを返す応答ごとにOPcache設定が異なる場合は失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 5 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
        {
          opcache: snapshot({ revalidateFrequency: 3 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /inconsistent/)
  })

  // date +%s は秒精度なので「初回は必ず 0s」とは仮定できない（秒の切り替わり直前に
  // 開始すると初回から 1s になる）。経過が settle を下回る試行が記録されたことだけを見る。
  it('再検証間隔が経過するまではID一致でも成功しない', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 2 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    const waited = [...result.stderr.matchAll(/settle=(\d+)s\/(\d+)s/g)].some(
      ([, elapsed, settle]) => Number(elapsed) < Number(settle),
    )

    assert.ok(waited, result.stderr)
  })

  it('再検証間隔の経過後にID一致が観測されれば成功する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 2 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stdout, /settled \d+s/)
  })

  // observe 段の目的は「enforce へ切り替える前に Web SAPI の実測値を記録する」こと（TADR-0006）。
  // 判定結果 safe:0 は「拡張が無い」「無効」「有効かつ freq=0」を区別できないため、証跡には
  // ならない。成功時こそ実測値そのものをログへ残す（失敗時だけ出しても記録目的を満たさない）。
  it('成功ログにOPcacheの実測値そのものを含める', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 0 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stdout, /"validateTimestamps":true/)
    assert.match(result.stdout, /"preloadConfigured":false/)
    assert.match(result.stdout, /"revalidateFrequency":0/)
  })

  // 応答スキーマを変更したデプロイでは、旧バイトコードが旧IDと旧形式で応答し得る。
  // 期待IDを返していない応答でゲートを適用すると、切り替わりを待たずに即失敗してしまう。
  it('旧形式の応答が旧IDを返す間はリトライし、期待IDの応答が届けば成功する', async () => {
    const legacyBody = JSON.stringify({
      opcache: {
        enabled: true,
        extensionLoaded: true,
        preload: '',
        revalidateFrequency: 0,
        validateTimestamps: true,
      },
      release: 'previous-release',
    })
    const result = await verifyReleaseHealth({
      releaseResponses: [
        { body: legacyBody, status: 200 },
        { body: legacyBody, status: 200 },
        { release: VALID_RELEASE_ID, status: 200 },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 2)
  })

  it('期待IDを返さない応答のOPcache設定では即座に失敗しない', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ validateTimestamps: false }),
          release: 'previous-release',
          status: 200,
        },
      ],
    })

    assert.equal(attemptCount(result), 10)
    assert.doesNotMatch(
      result.stderr,
      /::error::the web runtime cannot guarantee/,
    )
  })

  // 応答スキーマを変更したデプロイでは、旧バイトコードが index.php の定数だけを読んで
  // 「新IDだが旧 payload」を返し得る（リリースIDは定数由来で先に新しくなる）。opcache payload の
  // 構造は生成元バイトコードそのものなので、必須キーが揃わない間はゲートをかけずにリトライし、
  // 現行構造の応答で判定する。旧 payload は preloadConfigured を欠く点で現行と区別できる。
  it('期待ID一致でも現行スキーマの必須キーを欠く応答が続く間はリトライし現行構造で成功する', async () => {
    const legacyBody = JSON.stringify({
      opcache: {
        enabled: true,
        extensionLoaded: true,
        preload: '',
        revalidateFrequency: 0,
        validateTimestamps: true,
      },
      release: VALID_RELEASE_ID,
    })
    const result = await verifyReleaseHealth({
      releaseResponses: [
        { body: legacyBody, status: 200 },
        { body: legacyBody, status: 200 },
        { release: VALID_RELEASE_ID, status: 200 },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 2)
  })

  // 現行構造の応答なら、危険な設定は従来どおり即座に fail closed する
  // （構造ゲートで緩めすぎていないことの確認）。
  it('現行スキーマ構造の応答で危険な設定ならID一致で即座に失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ validateTimestamps: false }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 1)
    assert.equal(attemptCount(result), 0)
    assert.match(result.stderr, /::error::the web runtime cannot guarantee/)
  })

  it('OPcache拡張が未ロードなら待機なしで最初の試行で成功する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({
            enabled: null,
            extensionLoaded: false,
            preloadConfigured: null,
            revalidateFrequency: null,
            validateTimestamps: null,
          }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 0)
  })
})

// observe は「同一性ゲートを入れる前に各環境の実測値を測る」段階。ここで同一性を成功条件に
// 含めると、新エンドポイントが旧バイトコードの裏に隠れている環境（validate_timestamps=Off が
// まさにそれ）では測る前に健全なリリースを計画503へ引き戻してしまう。
describe('リリース同一性ゲート（observe）', () => {
  it('同一性を保証できない構成でもID一致なら成功し警告を残す', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [
        {
          opcache: snapshot({ validateTimestamps: false }),
          release: VALID_RELEASE_ID,
          status: 503,
        },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stderr, /::warning::observe mode/)
  })

  it('リリースIDが最後まで不一致でも到達性とアセット存在が満たされれば成功する', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 10)
    assert.match(
      result.stderr,
      /::warning::observe mode: release identity was not confirmed/,
    )
  })

  it('旧リリースにエンドポイントが無く観測できなくても成功する', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [
        { body: '<!doctype html><title>Not Found</title>', status: 404 },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stderr, /last observed none/)
  })

  // 期待IDでも現行スキーマの必須キーを欠く応答は同一性の確認に数えない。observe では
  // 到達性で成功しつつ「同一性は未確認」を記録する（旧構造の応答を誤って確定にしない）。
  it('期待ID一致でも現行スキーマの必須キーを欠く応答は同一性確認に数えない', async () => {
    const legacyBody = JSON.stringify({
      opcache: {
        enabled: true,
        extensionLoaded: true,
        preload: '',
        revalidateFrequency: 0,
        validateTimestamps: true,
      },
      release: VALID_RELEASE_ID,
    })
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [{ body: legacyBody, status: 200 }],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.equal(attemptCount(result), 10)
    assert.match(
      result.stderr,
      /::warning::observe mode: release identity was not confirmed/,
    )
  })

  // 旧仕様は2プローブが揃った時点で成功していた。同一性の観測を続けるために試行を
  // 重ねた結果、その後の一過性の 5xx で健全なリリースを引き戻してはならない。
  it('一度到達性とアセットが揃えばその後一過性の5xxが続いても成功する', async () => {
    const result = await verifyReleaseHealth({
      appStatus: [200, 500],
      enforcement: 'observe',
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.match(result.stdout, /on attempt 1/)
  })

  it('アセットが一度揃った後に404が続いても成功する', async () => {
    const result = await verifyReleaseHealth({
      assetStatus: [200, 404],
      enforcement: 'observe',
      releaseResponses: [{ release: 'previous-release', status: 200 }],
    })

    assert.equal(result.status, 0, result.stderr)
  })

  it('アプリが200を返さなければ同一性を問わず失敗する', async () => {
    const result = await verifyReleaseHealth({
      appStatus: 500,
      enforcement: 'observe',
      releaseResponses: [{ release: VALID_RELEASE_ID, status: 200 }],
    })

    assert.equal(result.status, 1)
  })

  it('アセットが200を返さなければ同一性を問わず失敗する', async () => {
    const result = await verifyReleaseHealth({
      assetStatus: 404,
      enforcement: 'observe',
      releaseResponses: [{ release: VALID_RELEASE_ID, status: 200 }],
    })

    assert.equal(result.status, 1)
  })
})

describe('リリースID焼き込み', () => {
  function stampFixture(frontController: string): string {
    const workspace = fs.mkdtempSync(
      path.join(os.tmpdir(), 'release-identity-stamp-'),
    )

    fs.mkdirSync(path.join(workspace, '.deploy/public'), { recursive: true })
    fs.writeFileSync(
      path.join(workspace, '.deploy/public/index.php'),
      frontController,
    )

    return workspace
  }

  async function stamp(
    frontController: string,
    releaseId: string = VALID_RELEASE_ID,
  ): Promise<ShellResult & { stamped: string; workspace: string }> {
    const workspace = stampFixture(frontController)
    const result = await runShellStep(
      stepRunScript('Stamp release identity'),
      { RELEASE_ID: releaseId },
      workspace,
    )

    return {
      ...result,
      stamped: fs.readFileSync(
        path.join(workspace, '.deploy/public/index.php'),
        'utf8',
      ),
      workspace,
    }
  }

  it('リリースIDをフロントコントローラのPHPリテラルとして書き込む', async () => {
    const result = await stamp(`<?php\n\n${PLACEHOLDER}\n`)

    assert.equal(result.status, 0, result.stderr)
    assert.match(
      result.stamped,
      new RegExp(
        `define\\('LARAVEL_DEPLOYMENT_RELEASE_ID', '${VALID_RELEASE_ID}'\\);`,
      ),
    )
    fs.rmSync(result.workspace, { force: true, recursive: true })
  })

  // rsync -a はモードも複製する。0600 になると、SSH ユーザーと Web/FastCGI ユーザーが
  // 異なる環境で index.php を読めず、全 HTTP リクエストが失敗する。
  it('フロントコントローラのパーミッションを変更しない', async () => {
    const workspace = stampFixture(`<?php\n\n${PLACEHOLDER}\n`)
    const stampedPath = path.join(workspace, '.deploy/public/index.php')

    fs.chmodSync(stampedPath, 0o644)

    const result = await runShellStep(
      stepRunScript('Stamp release identity'),
      { RELEASE_ID: VALID_RELEASE_ID },
      workspace,
    )

    assert.equal(result.status, 0, result.stderr)
    assert.equal(fs.statSync(stampedPath).mode & 0o777, 0o644)
    // 一時ファイルが配布物に残ると rsync でサーバーへ転送される。
    assert.deepEqual(fs.readdirSync(path.join(workspace, '.deploy/public')), [
      'index.php',
    ])
    fs.rmSync(workspace, { force: true, recursive: true })
  })

  it('リポジトリ側のフロントコントローラは書き換えない', async () => {
    const before = fs.readFileSync(FRONT_CONTROLLER_PATH, 'utf8')
    const result = await stamp(`<?php\n\n${PLACEHOLDER}\n`)

    assert.equal(fs.readFileSync(FRONT_CONTROLLER_PATH, 'utf8'), before)
    assert.ok(before.includes(PLACEHOLDER))
    fs.rmSync(result.workspace, { force: true, recursive: true })
  })

  it('プレースホルダが存在しない場合は書き換えずに失敗する', async () => {
    const source = "<?php\n\ndefine('LARAVEL_START', microtime(true));\n"
    const result = await stamp(source)

    assert.equal(result.status, 1)
    assert.equal(result.stamped, source)
    fs.rmSync(result.workspace, { force: true, recursive: true })
  })

  // 行数ではなく出現数で判定する。同じ行に2つ並んでいると grep -Fc は 1 を返し、
  // 置換は先頭だけに当たって空のプレースホルダが配布物に残る。
  it('プレースホルダが複数存在する場合は書き換えずに失敗する', async () => {
    for (const source of [
      `<?php\n\n${PLACEHOLDER}\n${PLACEHOLDER}\n`,
      `<?php\n\n${PLACEHOLDER} ${PLACEHOLDER}\n`,
    ]) {
      const result = await stamp(source)

      assert.equal(result.status, 1, result.stderr)
      assert.equal(result.stamped, source)
      assert.match(result.stderr, /found 2/)
      fs.rmSync(result.workspace, { force: true, recursive: true })
    }
  })

  it('不正な形式のリリースIDでは書き換えずに失敗する', async () => {
    const source = `<?php\n\n${PLACEHOLDER}\n`

    for (const releaseId of ['', 'a'.repeat(40), `${'A'.repeat(40)}-1-1`]) {
      const result = await stamp(source, releaseId)

      assert.equal(result.status, 1, `must reject ${JSON.stringify(releaseId)}`)
      assert.equal(result.stamped, source)
      fs.rmSync(result.workspace, { force: true, recursive: true })
    }
  })

  it('リポジトリのフロントコントローラはプレースホルダを1個だけ持つ', () => {
    const source = fs.readFileSync(FRONT_CONTROLLER_PATH, 'utf8')

    assert.equal(source.split(PLACEHOLDER).length - 1, 1)
  })
})

describe('デプロイ設定の検証', () => {
  function validate(
    env: Record<string, string>,
    inherited?: NodeJS.ProcessEnv,
  ): Promise<ShellResult> {
    return runShellStep(
      stepRunScript('Validate deployment configuration'),
      {
        HEALTHCHECK_URL: 'https://example.com/up',
        RELEASE_ID: VALID_RELEASE_ID,
        RELEASE_IDENTITY_ENFORCEMENT: 'enforce',
        ...env,
      },
      ROOT,
      inherited,
    )
  }

  it('デプロイ試行固有のリリースIDを受理する', async () => {
    assert.equal((await validate({})).status, 0)
  })

  it('リリースIDが未設定・空・commit SHAのみ・不正な文字なら拒否する', async () => {
    for (const releaseId of [
      '',
      'a'.repeat(40),
      `${'a'.repeat(40)}-1`,
      `${'A'.repeat(40)}-1-1`,
      `${'a'.repeat(40)}-1-1\n`,
      `${'a'.repeat(40)}-0-1`,
    ]) {
      assert.notEqual(
        (await validate({ RELEASE_ID: releaseId })).status,
        0,
        `must reject ${JSON.stringify(releaseId)}`,
      )
    }
  })

  it('列挙外の適用モードを拒否する', async () => {
    for (const enforcement of ['', 'soft', 'ENFORCE']) {
      assert.notEqual(
        (await validate({ RELEASE_IDENTITY_ENFORCEMENT: enforcement })).status,
        0,
        `must reject ${JSON.stringify(enforcement)}`,
      )
    }
  })

  const deploymentTarget = {
    DEPLOY_ENVIRONMENT: 'Production',
    GITHUB_REPOSITORY: 'owner/app',
    GITHUB_REPOSITORY_ID: '123456789',
  }

  it('設定変数が未設定・observe・リポジトリIDと環境名を伴う enforce なら受理する', async () => {
    for (const setting of [
      '',
      'observe',
      'enforce:owner/app@123456789:Production',
      // GitHub の Environment 名に空白制約は無い。`Pre Production` のような名前でも
      // 解決式は enforce を返すため、検証がそれより厳しくてはならない。
      'enforce:owner/app@123456789:Pre Production',
    ]) {
      assert.equal(
        (
          await validate({
            ...deploymentTarget,
            RELEASE_IDENTITY_ENFORCEMENT_SETTING: setting,
          })
        ).status,
        0,
        `must accept ${JSON.stringify(setting)}`,
      )
    }
  })

  it('リポジトリID・slug・環境名のいずれかを欠く設定変数を拒否する', async () => {
    // 素の "enforce" や環境名だけの値を許すと、organization スコープに置かれた 1 つの値が
    // 同名環境を持つ全派生アプリを enforce にできてしまう。slug だけでは、改名で空いた
    // slug を再利用した別アプリに届く余地が残るため、数値 ID も必須にする。
    for (const setting of [
      'enforce',
      'enforce:Production',
      'enforce:owner/app',
      'enforce:owner/app:Production',
      'enforce:owner/app@123456789',
      'enforce:owner/app@:Production',
      'enforce:@123456789:Production',
      'enforce:owner/app@123456789:',
      'Enforce:owner/app@123456789:Production',
      'on',
      // repository id は数値でなければならない。非数値を通すと「改名で空いた slug の
      // 再利用を数値 ID で塞ぐ」という設計が成立しない。
      'enforce:owner/app@abc:Production',
      // 余分な ":" 区切りを通すと、意図と違う環境名を名乗る値が well-formed に見える。
      'enforce:owner/app@123456789:Production:extra',
      // 末尾空白付きは == 比較に一致せず静かに observe へ落ちる。typo として弾く。
      'enforce:owner/app@123456789:Production ',
    ]) {
      assert.notEqual(
        (
          await validate({
            ...deploymentTarget,
            RELEASE_IDENTITY_ENFORCEMENT_SETTING: setting,
          })
        ).status,
        0,
        `must reject ${JSON.stringify(setting)}`,
      )
    }
  })

  it('他リポジトリ・改名前slug・他環境を名乗る設定変数は観測に畳まれたことを警告する', async () => {
    for (const setting of [
      'enforce:owner/app@123456789:Develop',
      'enforce:owner/other@987654321:Production',
      // 同じ slug でもリポジトリIDが違えば別リポジトリ（改名で空いた slug の再利用）。
      'enforce:owner/app@987654321:Production',
    ]) {
      const result = await validate({
        ...deploymentTarget,
        RELEASE_IDENTITY_ENFORCEMENT: 'observe',
        RELEASE_IDENTITY_ENFORCEMENT_SETTING: setting,
      })

      assert.equal(result.status, 0, `must accept ${JSON.stringify(setting)}`)
      assert.match(result.stderr, /::warning::/)
      assert.match(result.stderr, /enforce:owner\/app@123456789:Production/)
    }
  })

  it('継承したランナーのサマリへ書き込まない', async () => {
    // CI では ci-lint がこのテストを実行する。GITHUB_STEP_SUMMARY を継承すると、
    // テスト用の適用段階が lint ジョブの実サマリに混ざり、運用者の確認を誤らせる。
    // env 引数で空文字を渡すだけでは打ち消しが無くても通ってしまうため、
    // 「ランナーから継承される環境」側に実在のファイルを置いて検証する。
    const summaryDir = fs.mkdtempSync(
      path.join(os.tmpdir(), 'release-summary-'),
    )
    const summaryPath = path.join(summaryDir, 'summary.md')

    fs.writeFileSync(summaryPath, '')

    const result = await validate(
      {},
      { ...process.env, GITHUB_STEP_SUMMARY: summaryPath },
    )
    const leaked = fs.readFileSync(summaryPath, 'utf8')

    fs.rmSync(summaryDir, { force: true, recursive: true })

    assert.equal(result.status, 0)
    assert.equal(leaked, '', 'the run block wrote to the inherited summary')
  })
})

describe('再検証上限の一貫性', () => {
  const verifyScript = fs.readFileSync(VERIFY_SCRIPT_PATH, 'utf8')

  it('検証スクリプトの再検証上限がPHPの判定モジュールと一致する', () => {
    const scriptMax = /MAX_REVALIDATE_FREQUENCY = (\d+)/.exec(verifyScript)?.[1]
    const phpMax = /MAX_REVALIDATE_FREQUENCY = (\d+)/.exec(
      fs.readFileSync(RELEASE_VERIFICATION_PATH, 'utf8'),
    )?.[1]

    assert.ok(scriptMax !== undefined)
    assert.equal(scriptMax, phpMax)
  })

  // payload の必須キー集合は検証スクリプトの構造ゲートと OpcacheRevalidation::toArray の
  // 両方が持つ。片方だけ増減すると現行 payload が構造ゲートを通らず（or 旧 payload が通り）
  // デプロイ時にしか気づけないため、キー集合の一致をここで固定する。
  it('検証スクリプトの必須キー集合がPHPのopcache payloadと一致する', () => {
    const keyBlock = verifyScript.slice(
      verifyScript.indexOf('REQUIRED_OPCACHE_KEYS = ['),
      verifyScript.indexOf(
        ']',
        verifyScript.indexOf('REQUIRED_OPCACHE_KEYS = ['),
      ),
    )
    const scriptKeys = [...keyBlock.matchAll(/'([a-zA-Z]+)'/g)].map(
      (match) => match[1],
    )
    const phpKeys = [
      ...fs
        .readFileSync(OPCACHE_REVALIDATION_PATH, 'utf8')
        .matchAll(/'([a-zA-Z]+)' => \$this->/g),
    ].map((match) => match[1])

    assert.ok(scriptKeys.length > 0)
    assert.deepEqual([...scriptKeys].sort(), [...phpKeys].sort())
  })

  // deploy ジョブは pnpm install も setup-node もしない。外部依存が入ると、検証段階に
  // 到達した時点で初めて落ちる（= maintenance 解除後に健全なリリースを引き戻す）。
  it('検証スクリプトはNode同梱以外の依存を持たない', () => {
    const imports = [
      ...verifyScript.matchAll(/(?:^|\s)(?:import|from)\s*\(?\s*'([^']+)'/g),
    ].map((match) => match[1])

    for (const specifier of imports) {
      assert.ok(
        specifier?.startsWith('node:'),
        `verify-release-health.mjs must not import ${specifier}`,
      )
    }
  })
})

describe('HTTP契約', () => {
  // curl の --globoff は「WHATWG canonical な URL をリテラルに送る」ための指定だった。
  // fetch へ移した後も同じ受理集合であることを、サーバーが実際に受け取ったリクエスト
  // ターゲットで固定する。ここがずれると、Validate が通した URL を検証側が別表現で叩く。
  it('Validateが受理する記号付きURLをそのままのパスで要求する', async () => {
    const requested: string[] = []
    const server = http.createServer((request, response) => {
      requested.push(request.url ?? '')
      response.writeHead(200, { 'content-type': 'application/json' })
      response.end(
        JSON.stringify({ opcache: SAFE_OPCACHE, release: VALID_RELEASE_ID }),
      )
    })

    await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve))

    const port = (server.address() as AddressInfo).port
    const workspace = fs.mkdtempSync(path.join(os.tmpdir(), 'release-target-'))
    const buildDirectory = path.join(workspace, '.deploy/public/build')

    fs.mkdirSync(buildDirectory, { recursive: true })
    fs.writeFileSync(
      path.join(buildDirectory, 'manifest.json'),
      JSON.stringify({
        'resources/js/app.tsx': {
          file: 'assets/[canary]-DEAD.js',
          isEntry: true,
        },
      }),
    )

    try {
      await runShellStep(
        `node ${JSON.stringify(VERIFY_SCRIPT_PATH)}`,
        {
          HEALTHCHECK_URL: `http://127.0.0.1:${port}/health/[canary]`,
          RELEASE_HEALTH_RETRY_MS: '0',
          RELEASE_ID: VALID_RELEASE_ID,
          RELEASE_IDENTITY_ENFORCEMENT: 'enforce',
        },
        workspace,
      )
    } finally {
      // fetch は keep-alive で接続を残す。破棄しないと close が待たされる。
      server.closeAllConnections()
      await new Promise<void>((resolve) => server.close(() => resolve()))
      fs.rmSync(workspace, { force: true, recursive: true })
    }

    assert.ok(
      requested.includes('/health/[canary]'),
      `health probe target was re-encoded: ${JSON.stringify(requested)}`,
    )
    assert.ok(
      requested.includes('/build/assets/[canary]-DEAD.js'),
      `asset probe target was re-encoded: ${JSON.stringify(requested)}`,
    )
    assert.ok(requested.includes('/up/release'))
  })

  it('リダイレクトを追従せず、3xxのアセットを成功と見なさない', async () => {
    // 追従すると、欠落したアセットがリダイレクト先の 200 に解決して検証が骨抜きになる。
    const stub = await startStub({
      assetStatus: 302,
      releaseResponses: [
        { opcache: snapshot(), release: VALID_RELEASE_ID, status: 200 },
      ],
    })

    try {
      const result = await runShellStep(
        `node ${JSON.stringify(VERIFY_SCRIPT_PATH)}`,
        {
          HEALTHCHECK_URL: `${stub.origin}/up`,
          RELEASE_HEALTH_RETRY_MS: '0',
          RELEASE_ID: VALID_RELEASE_ID,
          RELEASE_IDENTITY_ENFORCEMENT: 'enforce',
        },
        verifyWorkspace,
      )

      assert.notEqual(result.status, 0)
      assert.match(result.stderr, /asset=302/)
    } finally {
      await stub.close()
    }
  })
})

describe('本文転送の完了確認', () => {
  // 旧実装の `curl -o /dev/null` は本文を EOF まで消費するため、途中で切れた転送は
  // 非ゼロ終了となり 200 として扱われなかった。fetch でヘッダーだけ見て転送を打ち切ると、
  // 壊れたアセット配信を「存在する」と誤判定して fail-closed の保証が破れる。
  async function runAgainstTruncatingAsset(): Promise<ShellResult> {
    const server = http.createServer((request, response) => {
      const url = request.url ?? '/'

      if (url.startsWith('/build/')) {
        // Content-Length を宣言し、本文の一部だけ送ってから「止まる」。
        // 即時に切断すると fetch の解決時点で既にエラーになり、本文を読むかどうかに
        // かかわらず失敗するため、実装差が出ない。ヘッダーが正常に解決した後で
        // 転送が完了しない配信こそが、読み切りでしか検出できない状態。
        response.writeHead(200, { 'Content-Length': '4096' })
        response.write('partial')
        setTimeout(() => request.socket.destroy(), 150)

        return
      }

      if (url === '/up/release') {
        response.writeHead(200, { 'Content-Type': 'application/json' })
        response.end(
          JSON.stringify({ opcache: SAFE_OPCACHE, release: VALID_RELEASE_ID }),
        )

        return
      }

      response.writeHead(200).end('up')
    })

    await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve))

    const port = (server.address() as AddressInfo).port

    try {
      return await runShellStep(
        `node ${JSON.stringify(VERIFY_SCRIPT_PATH)}`,
        {
          HEALTHCHECK_URL: `http://127.0.0.1:${port}/up`,
          RELEASE_HEALTH_RETRY_MS: '0',
          RELEASE_ID: VALID_RELEASE_ID,
          RELEASE_IDENTITY_ENFORCEMENT: 'enforce',
        },
        verifyWorkspace,
      )
    } finally {
      server.closeAllConnections()
      await new Promise<void>((resolve) => server.close(() => resolve()))
    }
  }

  it('ヘッダーが200でも本文が切れたアセットを成功と見なさない', async () => {
    const result = await runAgainstTruncatingAsset()

    assert.notEqual(result.status, 0)
    assert.doesNotMatch(result.stdout, /release health check passed/)
  })

  it('切れた転送はステータスではなく到達不能として記録される', async () => {
    const result = await runAgainstTruncatingAsset()

    assert.match(result.stderr, /asset=unreachable/)
  })
})

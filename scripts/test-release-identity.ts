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

/**
 * GitHub Actions と同じ bash -e で実行し、終了コードとログを検証する。
 * スタブ HTTP サーバは同じプロセスで動くため、同期実行するとイベントループが止まり
 * curl が応答を受け取れない。必ず非同期で待つ。
 */
function runShellStep(
  script: string,
  env: Record<string, string>,
  cwd: string = ROOT,
): Promise<ShellResult> {
  return new Promise((resolve, reject) => {
    const child = spawn('bash', ['-e', '-c', script], {
      cwd,
      env: { ...process.env, ...env },
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
  preload: string | null
  revalidateFrequency: number | null
  validateTimestamps: boolean | null
}

const SAFE_OPCACHE: OpcacheSnapshot = {
  enabled: true,
  extensionLoaded: true,
  preload: '',
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
  appStatus?: number
  assetStatus?: number
  /** 応答は試行順に消費し、尽きたら最後の応答を繰り返す。 */
  releaseResponses: ReleaseResponse[]
}

interface Stub {
  close: () => Promise<void>
  origin: string
  releaseRequestHeaders: http.IncomingHttpHeaders[]
}

async function startStub(options: StubOptions): Promise<Stub> {
  const releaseRequestHeaders: http.IncomingHttpHeaders[] = []
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
      response.writeHead(options.assetStatus ?? 200).end('asset')

      return
    }

    response.writeHead(options.appStatus ?? 200).end('up')
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
let stubbedPath = ''
let verifyWorkspace = ''

before(() => {
  const stubBin = fs.mkdtempSync(
    path.join(os.tmpdir(), 'release-identity-bin-'),
  )
  const sleepStub = path.join(stubBin, 'sleep')

  fs.writeFileSync(sleepStub, '#!/bin/sh\nexec /bin/sleep 0.25\n')
  fs.chmodSync(sleepStub, 0o755)

  stubbedPath = `${stubBin}:${process.env.PATH ?? ''}`

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
    const result = await runShellStep(
      stepRunScript('Verify release health'),
      {
        HEALTHCHECK_URL: `${stub.origin}/up`,
        PATH: stubbedPath,
        RELEASE_ID: options.releaseId ?? VALID_RELEASE_ID,
        RELEASE_OPCACHE_ENFORCEMENT: options.enforcement ?? 'enforce',
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
          opcache: snapshot({ preload: '/var/www/preload.php' }),
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

  it('応答ごとにOPcache設定が異なる場合は失敗する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        { release: 'previous-release', status: 200 },
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

  it('再検証間隔が経過するまではID一致でも成功しない', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 1 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.match(result.stderr, /health check attempt 1:.*settle=0s\/1s/)
  })

  it('再検証間隔の経過後にID一致が観測されれば成功する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({ revalidateFrequency: 1 }),
          release: VALID_RELEASE_ID,
          status: 200,
        },
      ],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.ok(attemptCount(result) >= 1)
  })

  it('OPcache拡張が未ロードなら待機なしで最初の試行で成功する', async () => {
    const result = await verifyReleaseHealth({
      releaseResponses: [
        {
          opcache: snapshot({
            enabled: null,
            extensionLoaded: false,
            preload: null,
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

describe('OPcacheゲート（observe）', () => {
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

  it('同一性を保証できない構成でリリースIDが不一致なら成功しない', async () => {
    const result = await verifyReleaseHealth({
      enforcement: 'observe',
      releaseResponses: [
        {
          opcache: snapshot({ validateTimestamps: false }),
          release: 'previous-release',
          status: 503,
        },
      ],
    })

    assert.notEqual(result.status, 0)
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

  it('プレースホルダが複数存在する場合は書き換えずに失敗する', async () => {
    const source = `<?php\n\n${PLACEHOLDER}\n${PLACEHOLDER}\n`
    const result = await stamp(source)

    assert.equal(result.status, 1)
    assert.equal(result.stamped, source)
    fs.rmSync(result.workspace, { force: true, recursive: true })
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
  function validate(env: Record<string, string>): Promise<ShellResult> {
    return runShellStep(stepRunScript('Validate deployment configuration'), {
      HEALTHCHECK_URL: 'https://example.com/up',
      RELEASE_ID: VALID_RELEASE_ID,
      RELEASE_OPCACHE_ENFORCEMENT: 'enforce',
      ...env,
    })
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
        (await validate({ RELEASE_OPCACHE_ENFORCEMENT: enforcement })).status,
        0,
        `must reject ${JSON.stringify(enforcement)}`,
      )
    }
  })
})

describe('再検証上限の一貫性', () => {
  it('ワークフローの再検証上限がPHPの判定モジュールと一致する', () => {
    const workflowMax = /max_revalidate_frequency=(\d+)/.exec(workflow)?.[1]
    const phpMax = /MAX_REVALIDATE_FREQUENCY = (\d+)/.exec(
      fs.readFileSync(RELEASE_VERIFICATION_PATH, 'utf8'),
    )?.[1]

    assert.ok(workflowMax !== undefined)
    assert.equal(workflowMax, phpMax)
  })
})

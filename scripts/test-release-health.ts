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

const VERIFY_SCRIPT_PATH = path.join(ROOT, 'scripts/verify-release-health.mjs')
const ASSET_FILE = 'assets/app-DEADBEEF.js'

interface ShellResult {
  status: number
  stderr: string
  stdout: string
}

// CI では ci-lint がこのテストを実行する。ランナーの書き込み先ファイルを子へ継承すると、
// テストの出力が lint ジョブの実サマリや環境へ混ざり得るので打ち消しておく。
const RUNNER_WRITE_TARGETS = {
  GITHUB_STEP_SUMMARY: '',
  GITHUB_ENV: '',
  GITHUB_OUTPUT: '',
  GITHUB_PATH: '',
} as const

/**
 * delivery.yml が実際に呼ぶのと同じ形で CLI を起動し、終了コードとログを検証する。
 * スタブ HTTP サーバは同じプロセスで動くため、同期実行するとイベントループが止まり
 * 応答を受け取れない。必ず非同期で待つ。
 */
function runShellStep(
  script: string,
  env: Record<string, string>,
  cwd: string = ROOT,
): Promise<ShellResult> {
  return new Promise((resolve, reject) => {
    const child = spawn('bash', ['-e', '-c', script], {
      cwd,
      env: { ...process.env, ...RUNNER_WRITE_TARGETS, ...env },
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

interface StubOptions {
  /** 単一値のほか、試行順に消費する系列も渡せる（尽きたら最後の値を繰り返す）。 */
  appStatus?: number | number[]
  /**
   * 3xx のアセット応答へ載せる `Location`。追従の有無を検出するには必須で、
   * 省くと WHATWG fetch は redirect モードに関係なく追従しない（locationURL が
   * null なら that response をそのまま返す）ため、非追従の検証が空洞になる。
   */
  assetLocation?: string
  assetStatus?: number | number[]
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
  appRequestHeaders: http.IncomingHttpHeaders[]
  assetRequestHeaders: http.IncomingHttpHeaders[]
  close: () => Promise<void>
  origin: string
}

async function startStub(options: StubOptions): Promise<Stub> {
  const appRequestHeaders: http.IncomingHttpHeaders[] = []
  const assetRequestHeaders: http.IncomingHttpHeaders[] = []
  let appRequests = 0
  let assetRequests = 0
  const server = http.createServer((request, response) => {
    const url = request.url ?? '/'

    if (url.startsWith('/build/')) {
      assetRequestHeaders.push(request.headers)
      response
        .writeHead(
          statusAt(options.assetStatus, assetRequests),
          options.assetLocation === undefined
            ? {}
            : { Location: options.assetLocation },
        )
        .end('asset')
      assetRequests += 1

      return
    }

    appRequestHeaders.push(request.headers)
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
    appRequestHeaders,
    assetRequestHeaders,
    origin: `http://127.0.0.1:${address.port}`,
  }
}

/**
 * リトライ待機は RELEASE_HEALTH_RETRY_MS で実測時間を縮める（既定は 10 回 × 15 秒）。
 * 0 にはしない: 「初回で揃わなくても後の試行で揃えば成功する」経路を実時間で通したい。
 */
let verifyWorkspace = ''

before(() => {
  verifyWorkspace = fs.mkdtempSync(path.join(os.tmpdir(), 'release-health-'))

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
  basicAuth?: string
}

async function verifyReleaseHealth(options: VerifyOptions): Promise<
  ShellResult & {
    appRequestHeaders: http.IncomingHttpHeaders[]
    assetRequestHeaders: http.IncomingHttpHeaders[]
  }
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
        // 未指定でもキーを省かない。省くと親プロセスの export 値が子へ残り、
        // 「未設定」ケースが環境依存で落ちて資格情報が assert 差分に出る。
        HEALTHCHECK_BASIC_AUTH: options.basicAuth ?? '',
      },
      verifyWorkspace,
    )

    return {
      ...result,
      appRequestHeaders: stub.appRequestHeaders,
      assetRequestHeaders: stub.assetRequestHeaders,
    }
  } finally {
    await stub.close()
  }
}

describe('リリース健全性検証', () => {
  // 成功条件は2つの HTTP 観測だけ（TADR-0006）。到達性は web 経路（rewrite → PHP-FPM →
  // Composer autoload → フレームワーク起動）が生きていることを示し、アセット存在は
  // 今回の manifest が参照するビルド成果物が配信されていることを示す。/up は @vite を
  // 読まないため、アセット欠落は /up の 200 だけでは検出できない。
  it('アプリとアセットがともに200なら成功する', async () => {
    const result = await verifyReleaseHealth({})

    assert.equal(result.status, 0, result.stderr)
  })

  // OPcache の再検証待ちは delivery.yml の `Wait for OPcache revalidation` が maintenance
  // 解除より前に行う。ここで待ち直すと、計画503が明けた後の無防備な区間が延びるだけになる。
  // 解除直後の最初の probe は即座に投げる契約を、実時間で固定する。
  it('解除後の最初のプローブを待機なしで投げる', async () => {
    const startedAt = Date.now()
    const result = await verifyReleaseHealth({})
    const elapsed = Date.now() - startedAt

    assert.equal(result.status, 0, result.stderr)
    assert.ok(
      elapsed < 5_000,
      `the first probe must not be delayed by a settle wait, took ${elapsed}ms`,
    )
  })

  // 揃った時点で打ち切らないと、健全なリリースでも 10 回 × 15 秒を待つ。
  it('両プローブが揃った時点で残りの試行を行わない', async () => {
    const result = await verifyReleaseHealth({})

    assert.equal(result.status, 0, result.stderr)
    assert.equal(result.appRequestHeaders.length, 1)
    assert.equal(result.assetRequestHeaders.length, 1)
  })

  // maintenance 解除直後は post-deploy の反映途中で一過性の失敗があり得る。
  // 初回で落とすと健全なリリースを withdraw する。
  it('最初の試行で揃わなくても後の試行で揃えば成功する', async () => {
    const result = await verifyReleaseHealth({
      appStatus: [503, 200],
      assetStatus: [404, 200],
    })

    assert.equal(result.status, 0, result.stderr)
    assert.ok(result.appRequestHeaders.length >= 2)
  })

  // composer install / migration / .htaccess の失敗はここに出る。
  it('アプリが200を返さなければ失敗する', async () => {
    const result = await verifyReleaseHealth({ appStatus: 500 })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /app=500/)
  })

  // ビルド成果物の欠落・転送漏れはここに出る。/up は 200 のままなので、
  // アセット側を独立に見ないと検出できない。
  it('アセットが200を返さなければ失敗する', async () => {
    const result = await verifyReleaseHealth({ assetStatus: 404 })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /asset=404/)
  })

  // Validate は https 強制までしか静的に判定できない。www→apex のようなホスト側
  // リダイレクトは実際に叩くまで分からないため、3xx が続いたら切り分けを出す。
  it('アプリが3xxを返し続ける場合はcanonical URLの設定を促す', async () => {
    const result = await verifyReleaseHealth({ appStatus: 301 })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /canonical/)
  })

  // develop のようにディレクトリ全体が Basic 認証で保護された環境では、2プローブとも
  // 認証が要る。1つでも欠けると 401 になり、到達性を満たせず健全なリリースが
  // withdraw される（派生アプリの develop が実際にこの構成）。
  it('Basic認証secretを設定すると2プローブとも認証ヘッダーを付ける', async () => {
    const result = await verifyReleaseHealth({
      basicAuth: 'deploy:s3cret',
    })
    const expected = `Basic ${Buffer.from('deploy:s3cret').toString('base64')}`

    assert.equal(result.status, 0, result.stderr)
    assert.equal(result.appRequestHeaders[0]?.authorization, expected)
    assert.equal(result.assetRequestHeaders[0]?.authorization, expected)
  })

  it('Basic認証secretが未設定なら認証ヘッダーを送らない', async () => {
    const result = await verifyReleaseHealth({})

    assert.equal(result.appRequestHeaders[0]?.authorization, undefined)
    assert.equal(result.assetRequestHeaders[0]?.authorization, undefined)
  })

  // 親プロセスが HEALTHCHECK_BASIC_AUTH を export している環境でも結果を変えない。
  // 継承を許すと「未設定」ケースが実環境依存で落ち、assert の差分に Base64 化した
  // 実資格情報が出力され得る（RUNNER_WRITE_TARGETS と同じ隔離方針）。
  it('親環境のBasic認証値を継承しない', async () => {
    const previous = process.env.HEALTHCHECK_BASIC_AUTH

    process.env.HEALTHCHECK_BASIC_AUTH = 'inherited:leaked'

    try {
      const result = await verifyReleaseHealth({})

      assert.equal(result.appRequestHeaders[0]?.authorization, undefined)
      assert.equal(result.assetRequestHeaders[0]?.authorization, undefined)
    } finally {
      if (previous === undefined) {
        delete process.env.HEALTHCHECK_BASIC_AUTH
      } else {
        process.env.HEALTHCHECK_BASIC_AUTH = previous
      }
    }
  })

  it('資格情報をログへ出さない', async () => {
    const result = await verifyReleaseHealth({
      appStatus: 401,
      basicAuth: 'deploy:s3cret',
    })

    assert.doesNotMatch(result.stdout, /s3cret/)
    assert.doesNotMatch(result.stderr, /s3cret/)
  })

  it('401が続く場合はBasic認証secretの設定を促す', async () => {
    const result = await verifyReleaseHealth({
      appStatus: 401,
    })

    assert.equal(result.status, 1)
    assert.match(result.stderr, /HEALTHCHECK_BASIC_AUTH/)
  })
})

describe('HTTP契約', () => {
  // curl の --globoff は「WHATWG canonical な URL をリテラルに送る」ための指定だった。
  // fetch へ移した後も同じ受理集合であることを、サーバーが実際に受け取ったリクエスト
  // ターゲットで固定する。ここがずれると、Validate が通した URL を検証側が別表現で叩く。
  // health URL のパスは Validate が `/up` に固定するため記号を含み得ないが、アセット名は
  // manifest 由来でアプリが決めるので `[ ]` を含み得る。curl の --globoff は「その URL を
  // リテラルに送る」ための指定だった。fetch へ移した後も再エンコードや glob 展開をせず
  // 同じリクエストターゲットを送ることを、サーバーが実際に受け取った値で固定する。
  it('記号を含むアセット名をそのままのパスで要求する', async () => {
    const requested: string[] = []
    const server = http.createServer((request, response) => {
      requested.push(request.url ?? '')
      response.writeHead(200, { 'content-type': 'application/json' })
      response.end('up')
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
          HEALTHCHECK_URL: `http://127.0.0.1:${port}/up`,
          RELEASE_HEALTH_RETRY_MS: '0',
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
      requested.includes('/up'),
      `health probe target was re-encoded: ${JSON.stringify(requested)}`,
    )
    assert.ok(
      requested.includes('/build/assets/[canary]-DEAD.js'),
      `asset probe target was re-encoded: ${JSON.stringify(requested)}`,
    )
  })

  it('リダイレクトを追従せず、3xxのアセットを成功と見なさない', async () => {
    // 追従すると、欠落したアセットがリダイレクト先の 200 に解決して検証が骨抜きになる。
    // Location は必ず 200 を返す先（ここでは /up）を指すこと。付けないと fetch は
    // redirect モードに関係なく追従せず、`redirect: 'manual'` を外しても 302 のままに
    // なるため、非追従を外した回帰をこのテストが検出できなくなる。
    const stub = await startStub({
      assetLocation: '/up',
      assetStatus: 302,
    })

    try {
      const result = await runShellStep(
        `node ${JSON.stringify(VERIFY_SCRIPT_PATH)}`,
        {
          HEALTHCHECK_URL: `${stub.origin}/up`,
          RELEASE_HEALTH_RETRY_MS: '0',
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

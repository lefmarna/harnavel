// リリース健全性検証（TADR-0006）。
//
// deploy ジョブは pnpm install も setup-node もしないため、このファイルは**依存を持たない**。
// ランナー同梱の Node だけで動く（tsx / 外部パッケージ / jq を使わない）。
//
// curl から fetch へ移した際に維持しなければならない契約:
// - リダイレクトを追従しない（redirect: 'manual'）。追従すると、欠落したビルドアセットが
//   別の 200 応答へ解決してアセット検証が骨抜きになる。
// - 各プローブに 30 秒のタイムアウト。
// - ネットワーク失敗はセンチネル（unreachable / 000）へ落として**リトライを継続**する。
//   例外を投げると、一過性の DNS / TLS 失敗で検証全体が即死する。

export const MAX_ATTEMPTS = 10
export const RETRY_INTERVAL_MS = 15_000
export const PROBE_TIMEOUT_MS = 30_000

// OPcache の再検証待ちはここではなく delivery.yml の `Wait for OPcache revalidation` が行う。
// maintenance 解除より前に待つ必要があるため（解除後に待つと、旧バイトコードと新スキーマが
// 組み合わさる窓へ実ユーザーのリクエストが入る）。解除後の最初の probe は即座に実行する。

/**
 * 終了前に stdout/stderr の送出を待つ上限。ここで待ち続けると健全なリリースが
 * withdraw されるため、drain は必ず打ち切れるようにする。
 */
export const DRAIN_TIMEOUT_MS = 2_000

/**
 * 書き込み済みデータが送出されるまで待つ。空書き込みのコールバックは順序保証により
 * 先行する書き込みが片付いた後に発火する。
 */
function drainStream(stream) {
  return new Promise((resolve) => {
    stream.write('', () => resolve())
  })
}

/** オリジンは / ・ ? ・ # の手前まで。query / fragment は Validate 済みだが多層防御。 */
export function originOf(healthcheckUrl) {
  const match = /^(https?:\/\/[^/?#]+)/.exec(healthcheckUrl)

  if (match === null) {
    throw new Error(
      `HEALTHCHECK_URL is not an absolute http(s) URL: ${healthcheckUrl}`,
    )
  }

  return match[1]
}

/** jq の `first(.[] | select(.isEntry == true) | .file) // first(.[] | .file)` と同じ選択。 */
export function selectAsset(manifest) {
  const entries = Object.values(manifest ?? {})
  const entry = entries.find(
    (item) => item?.isEntry === true && typeof item.file === 'string',
  )

  if (entry !== undefined) {
    return entry.file
  }

  const first = entries.find((item) => typeof item?.file === 'string')

  if (first === undefined) {
    throw new Error('the deployed manifest has no built asset to probe')
  }

  return first.file
}

const defaultSleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * Basic 認証ヘッダー。develop のようにディレクトリ全体が保護された環境では、
 * **2プローブすべて**に付ける必要がある。1つでも欠けると 401 になり、
 * 到達性を満たせずに健全なリリースが withdraw される。
 * 資格情報はヘッダーにだけ載せ、ログ・エラー文には出さない。
 */
function basicAuthHeaders(basicAuth) {
  return basicAuth === ''
    ? {}
    : { Authorization: `Basic ${Buffer.from(basicAuth).toString('base64')}` }
}

/**
 * ステータスだけが必要なプローブ。ネットワーク失敗は 'unreachable' に落とす。
 */
async function probeStatus(fetchImpl, url, headers = {}) {
  try {
    const response = await fetchImpl(url, {
      headers,
      redirect: 'manual',
      signal: AbortSignal.timeout(PROBE_TIMEOUT_MS),
    })

    // 本文は捨てるが、必ず EOF まで読み切る（curl の -o /dev/null 相当）。
    // cancel() では転送を中断してしまい、ヘッダーだけ 200 を返して本文が切れる・
    // 止まる配信を「アセットは配信されている」と誤判定する。読み切ることで、
    // 切断・Content-Length 不足・タイムアウトが例外となり unreachable に倒れる。
    // 接続がプールへ返るのも読み切りの副次効果（放置するとプロセスが終了できない）。
    if (response.body !== null) {
      for await (const chunk of response.body) {
        void chunk
      }
    }

    return String(response.status)
  } catch {
    return 'unreachable'
  }
}

/**
 * @returns {Promise<{ok: boolean, message: string}>}
 */
export async function verifyReleaseHealth({
  basicAuth = '',
  fetchImpl = fetch,
  healthcheckUrl,
  logError = (line) => console.error(line),
  manifest,
  sleep = defaultSleep,
}) {
  const origin = originOf(healthcheckUrl)
  const assetUrl = `${origin}/build/${selectAsset(manifest)}`
  const authHeaders = basicAuthHeaders(basicAuth)

  let appStatus = ''

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    appStatus = await probeStatus(fetchImpl, healthcheckUrl, authHeaders)
    const assetStatus = await probeStatus(fetchImpl, assetUrl, authHeaders)

    if (appStatus === '200' && assetStatus === '200') {
      return {
        message: `release health check passed (app 200, built asset 200)`,
        ok: true,
      }
    }

    logError(
      `health check attempt ${attempt}: app=${appStatus} asset=${assetStatus} (${assetUrl})`,
    )

    if (attempt < MAX_ATTEMPTS) {
      await sleep(RETRY_INTERVAL_MS)
    }
  }

  logError('release health check failed')

  // 3xx は「リリースが壊れている」ではなく「URL が canonical ではない」ことが多い。
  // Validate は https 強制までしか静的に判定できず、www→apex のようなホスト側
  // リダイレクトは実際に叩くまで分からないため、ここで切り分けを出す。
  if (/^3\d\d$/.test(appStatus)) {
    logError(
      'the health endpoint returned a redirect; HEALTHCHECK_URL must be the canonical URL because redirects are not followed',
    )
  }

  // develop のように Basic 認証で保護された環境では、secret 未設定だと全プローブが 401 になる。
  if (appStatus === '401') {
    logError(
      'the health endpoint required authentication; set the HEALTHCHECK_BASIC_AUTH environment secret to user:pass for a Basic-auth protected environment such as develop',
    )
  }

  return { message: '', ok: false }
}

/** CLI エントリ。delivery.yml はこのファイルを 1 行で呼ぶだけにする。 */
export async function main({ env = process.env, readFile } = {}) {
  const manifestPath =
    env.RELEASE_MANIFEST ?? '.deploy/public/build/manifest.json'
  const read = readFile ?? (await import('node:fs')).readFileSync
  const manifest = JSON.parse(read(manifestPath, 'utf8'))

  // リトライ間隔の上書きは契約テスト専用。実 CLI をそのまま回して 10 回 × 15 秒を
  // 待たずに済ませるためだけにあり、デプロイでは設定しない（既定が正典）。
  const override = Number(env.RELEASE_HEALTH_RETRY_MS)
  const interval =
    env.RELEASE_HEALTH_RETRY_MS !== undefined &&
    Number.isFinite(override) &&
    override >= 0
      ? override
      : RETRY_INTERVAL_MS

  const result = await verifyReleaseHealth({
    // 資格情報は URL へ埋め込めない（Validate が userinfo 付き URL を拒否する）。
    // Basic 認証で保護された環境だけが設定する任意の secret。
    basicAuth: env.HEALTHCHECK_BASIC_AUTH ?? '',
    healthcheckUrl: env.HEALTHCHECK_URL ?? '',
    manifest,
    sleep: () => defaultSleep(interval),
  })

  if (result.message !== '') {
    if (result.ok) {
      console.log(result.message)
    } else {
      console.error(result.message)
    }
  }

  return result.ok ? 0 : 1
}

if (
  process.argv[1] !== undefined &&
  import.meta.url === `file://${process.argv[1]}`
) {
  const exitCode = await main()

  // 明示的に終了する。fetch は keep-alive の接続をプールに残すため、判定が出た後も
  // プロセスが生きたまま数秒〜無期限に居座り得る（process.exitCode に変えると再発する）。
  // ただし process.exit() は未送出の stdout/stderr を切り捨てる。パイプ宛ての書き込みが
  // 同期かどうかはプラットフォーム依存なので前提にできず、ログを落とすと
  // 失敗原因などの判断材料が失われる。明示的に drain してから終了する。
  // drain 自体では止まらない: ここで固まると健全なリリースが withdraw されるため、
  // 上限を設けて打ち切る。
  await Promise.race([
    Promise.all([drainStream(process.stdout), drainStream(process.stderr)]),
    new Promise((resolve) => setTimeout(resolve, DRAIN_TIMEOUT_MS)),
  ])

  process.exit(exitCode)
}

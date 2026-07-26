// リリース健全性検証（TADR-0006）。
//
// deploy ジョブは pnpm install も setup-node もしないため、このファイルは**依存を持たない**。
// ランナー同梱の Node だけで動く（tsx / 外部パッケージ / jq を使わない）。
//
// curl から fetch へ移した際に維持しなければならない契約:
// - リダイレクトを追従しない（redirect: 'manual'）。追従すると、欠落したビルドアセットが
//   別の 200 応答へ解決してアセット検証が骨抜きになる。
// - 各プローブに 30 秒のタイムアウト。
// - リリースプローブだけキャッシュ無効化ヘッダーを送る。
// - ネットワーク失敗はセンチネル（unreachable / 000）へ落として**リトライを継続**する。
//   例外を投げると、一過性の DNS / TLS 失敗で検証全体が即死する。
// - 10 回 × 15 秒、OPcache の settle 判定、observe の「いずれかの試行で 2 プローブが
//   揃えば成功」を維持する。

/** app/Support/ReleaseVerification.php の MAX_REVALIDATE_FREQUENCY と一致させる。 */
export const MAX_REVALIDATE_FREQUENCY = 60

/** OpcacheRevalidation::toArray() が返すキー。構造そのものが版の証拠になる。 */
export const REQUIRED_OPCACHE_KEYS = [
  'extensionLoaded',
  'enabled',
  'validateTimestamps',
  'revalidateFrequency',
  'preloadConfigured',
]

export const MAX_ATTEMPTS = 10
export const RETRY_INTERVAL_MS = 15_000
export const PROBE_TIMEOUT_MS = 30_000

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

/** キー順に依存しない比較のための正規化（jq -S 相当）。 */
export function canonicalJson(value) {
  if (Array.isArray(value)) {
    return `[${value.map(canonicalJson).join(',')}]`
  }

  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value)
      .sort()
      .map((key) => `${JSON.stringify(key)}:${canonicalJson(value[key])}`)
      .join(',')}}`
  }

  return JSON.stringify(value) ?? 'null'
}

export function hasCurrentSchema(snapshot) {
  return (
    snapshot !== null &&
    typeof snapshot === 'object' &&
    !Array.isArray(snapshot) &&
    REQUIRED_OPCACHE_KEYS.every((key) => key in snapshot)
  )
}

/**
 * 現行スキーマの payload だけを判定する。戻り値は `safe:<秒>` / `invalid`。
 * 「待てば直る」以外はすべて invalid（fail closed）。
 */
export function opcacheVerdict(snapshot, max = MAX_REVALIDATE_FREQUENCY) {
  if (snapshot.extensionLoaded === false) {
    return 'safe:0'
  }

  if (snapshot.extensionLoaded !== true) {
    return 'invalid'
  }

  if (snapshot.enabled === false) {
    return 'safe:0'
  }

  if (snapshot.enabled !== true) {
    return 'invalid'
  }

  if (snapshot.validateTimestamps !== true) {
    return 'invalid'
  }

  // preload されたコードはプロセス終了までタイムスタンプ再検証の対象外になる。
  if (snapshot.preloadConfigured !== false) {
    return 'invalid'
  }

  if (typeof snapshot.revalidateFrequency !== 'number') {
    return 'invalid'
  }

  if (snapshot.revalidateFrequency < 0 || snapshot.revalidateFrequency > max) {
    return 'invalid'
  }

  return `safe:${snapshot.revalidateFrequency}`
}

const defaultSleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * ステータスだけが必要なプローブ。ネットワーク失敗は 'unreachable' に落とす。
 */
async function probeStatus(fetchImpl, url) {
  try {
    const response = await fetchImpl(url, {
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
 * リリースプローブ。中間キャッシュが返した旧リリースの応答を同一性の証拠にしない。
 * ネットワーク失敗は curl の `printf '\n000'` と同じく status 000 / 本文なしにする。
 */
async function probeRelease(fetchImpl, url) {
  try {
    const response = await fetchImpl(url, {
      headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
      redirect: 'manual',
      signal: AbortSignal.timeout(PROBE_TIMEOUT_MS),
    })

    return { body: await response.text(), status: String(response.status) }
  } catch {
    return { body: '', status: '000' }
  }
}

function parseReleaseBody(body) {
  let parsed

  try {
    parsed = JSON.parse(body)
  } catch {
    return { observedRelease: '', snapshot: undefined }
  }

  if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
    return { observedRelease: '', snapshot: undefined }
  }

  return {
    observedRelease: typeof parsed.release === 'string' ? parsed.release : '',
    // キーの有無で「観測できなかった」と「旧形式だった」を区別する。
    snapshot: 'opcache' in parsed ? parsed.opcache : undefined,
  }
}

/**
 * @returns {Promise<{ok: boolean, message: string}>}
 */
export async function verifyReleaseHealth({
  enforcement,
  fetchImpl = fetch,
  healthcheckUrl,
  logError = (line) => console.error(line),
  logInfo = (line) => console.log(line),
  manifest,
  now = () => Date.now(),
  releaseId,
  sleep = defaultSleep,
}) {
  if (!releaseId) {
    return {
      message:
        '::error::RELEASE_ID is required; an empty expectation would match an empty response',
      ok: false,
    }
  }

  if (enforcement !== 'observe' && enforcement !== 'enforce') {
    return {
      message:
        "::error::RELEASE_IDENTITY_ENFORCEMENT must be 'observe' or 'enforce'",
      ok: false,
    }
  }

  const origin = originOf(healthcheckUrl)
  const assetUrl = `${origin}/build/${selectAsset(manifest)}`
  const releaseUrl = `${origin}/up/release`

  // リモートへの書き込みはすべて完了している。ここを基点に再検証期限を数える。
  const settleFrom = now()
  let firstSnapshot = ''
  // observe の成功判定は「いずれかの試行で到達性とアセットが揃った」ことに固定する。
  // 最後の試行だけを見ると、一度健全と観測した後の一過性の 5xx で、旧仕様なら成功して
  // いたリリースを maintenance へ引き戻してしまう。
  let reachableAttempt = 0
  let appStatus = ''
  let observedRelease = ''
  let opcacheState = 'unobserved'
  let lastSnapshotJson = ''

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    appStatus = await probeStatus(fetchImpl, healthcheckUrl)

    const assetStatus = await probeStatus(fetchImpl, assetUrl)
    const release = await probeRelease(fetchImpl, releaseUrl)
    const parsed = parseReleaseBody(release.body)

    observedRelease = parsed.observedRelease

    const schemaCurrent = hasCurrentSchema(parsed.snapshot)
    const snapshotJson =
      parsed.snapshot === undefined ? '' : canonicalJson(parsed.snapshot)

    lastSnapshotJson = snapshotJson

    if (parsed.snapshot === undefined) {
      // 旧リリースにはこのエンドポイントが無い。まだ切り替わっていないだけなので待つ。
      opcacheState = 'unobserved'
    } else if (!schemaCurrent) {
      // opcache キーはあるが現行スキーマの必須キーを欠く。旧バイトコードが新IDだけ読んで
      // 旧形式を返した状態であり得るので、ゲートを適用せず切り替わりを待つ。
      opcacheState = 'unrecognized-schema'
    } else {
      opcacheState = opcacheVerdict(parsed.snapshot)
    }

    // 今回のリリースの実行環境を語れるのは「今回のIDを返し、かつ現行スキーマの payload
    // を持つ応答」だけ。リリースIDは index.php の定数由来なので旧バイトコードでも新IDを
    // 返し得るが、opcache payload の構造は生成元バイトコードそのものなので、構造が現行
    // なら opcache を判定・firstSnapshot 化してよい。ID一致でも構造が古い/欠ける応答は
    // 「まだ切り替わっていない」とみなしリトライする。
    // そうしないと、この応答形式を変更するたびに初回デプロイが即失敗する。
    let authoritative = false

    if (schemaCurrent && observedRelease === releaseId) {
      authoritative = true

      if (firstSnapshot !== '' && snapshotJson !== firstSnapshot) {
        // 実行単位ごとに設定が異なるなら、環境が均一であることを証明できない。
        opcacheState = 'inconsistent'
      } else {
        firstSnapshot = snapshotJson
      }
    }

    let settleSeconds = 0

    if (authoritative) {
      if (opcacheState.startsWith('safe:')) {
        settleSeconds = Number(opcacheState.slice('safe:'.length))
      } else if (enforcement === 'enforce') {
        return {
          message: `::error::the web runtime cannot guarantee release identity (${opcacheState}); OPcache must revalidate timestamps within ${MAX_REVALIDATE_FREQUENCY}s without preloading application code: ${snapshotJson}`,
          ok: false,
        }
      } else {
        logError(
          `::warning::observe mode: the web runtime cannot guarantee release identity (${opcacheState}); record this before switching RELEASE_IDENTITY_ENFORCEMENT to enforce: ${snapshotJson}`,
        )
      }
    }

    const elapsed = Math.floor((now() - settleFrom) / 1000)
    // observe では、同一性を保証できない構成の 503 応答も観測結果として受け入れる。
    const releaseObserved =
      release.status === '200' ||
      (enforcement === 'observe' &&
        release.status === '503' &&
        observedRelease !== '')

    if (
      appStatus === '200' &&
      assetStatus === '200' &&
      reachableAttempt === 0
    ) {
      reachableAttempt = attempt
    }

    if (
      appStatus === '200' &&
      assetStatus === '200' &&
      releaseObserved &&
      authoritative &&
      elapsed >= settleSeconds
    ) {
      return {
        // 判定結果（safe:0）は「拡張なし」「無効」「有効かつ freq=0」を区別できないため、
        // enforce へ切り替える前の証跡には実測値そのものが要る（TADR-0006 の段階導入）。
        message: `release health check passed (app 200, built asset 200, release ${releaseId} serving, opcache ${opcacheState}, values ${firstSnapshot}, settled ${elapsed}s)`,
        ok: true,
      }
    }

    logError(
      `health check attempt ${attempt}: app=${appStatus} asset=${assetStatus} (${assetUrl}) release=${release.status}/${observedRelease || 'none'} expected=${releaseId} opcache=${opcacheState} settle=${elapsed}s/${settleSeconds}s`,
    )

    if (attempt < MAX_ATTEMPTS) {
      await sleep(RETRY_INTERVAL_MS)
    }
  }

  // observe は導入前に実測値を記録するための段階。リリース同一性はまだ成功条件に
  // 含めない: 新エンドポイントは旧バイトコードの裏に隠れて現れないことがあり
  // （validate_timestamps=Off ではまさにその状態）、ここで失敗させると測る前に
  // 健全なリリースを計画503へ引き戻してしまう。判定は旧仕様と同じ2プローブのままで、
  // 一度でも揃った試行があれば成功とする（観測のためのリトライで結果を変えない）。
  if (enforcement === 'observe' && reachableAttempt !== 0) {
    logError(
      `::warning::observe mode: release identity was not confirmed (expected ${releaseId}, last observed ${observedRelease || 'none'}, opcache ${opcacheState}, values ${firstSnapshot || lastSnapshotJson || 'none'}); record the web runtime values from this log before switching RELEASE_IDENTITY_ENFORCEMENT to enforce`,
    )

    return {
      message: `release health check passed without release identity (app 200, built asset 200 on attempt ${reachableAttempt}, observe mode)`,
      ok: true,
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

  if (observedRelease !== releaseId) {
    logError(
      `the deployed release did not serve its own identity: expected ${releaseId}, last observed ${observedRelease || 'none'}`,
    )
  }

  void logInfo

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
    enforcement: env.RELEASE_IDENTITY_ENFORCEMENT ?? '',
    healthcheckUrl: env.HEALTHCHECK_URL ?? '',
    manifest,
    releaseId: env.RELEASE_ID ?? '',
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
  // 同期かどうかはプラットフォーム依存なので前提にできず、observe の証跡（OPcache 実測値）
  // を落とすと enforce へ切り替える判断材料が失われる。明示的に drain してから終了する。
  // drain 自体では止まらない: ここで固まると健全なリリースが withdraw されるため、
  // 上限を設けて打ち切る。
  await Promise.race([
    Promise.all([drainStream(process.stdout), drainStream(process.stderr)]),
    new Promise((resolve) => setTimeout(resolve, DRAIN_TIMEOUT_MS)),
  ])

  process.exit(exitCode)
}

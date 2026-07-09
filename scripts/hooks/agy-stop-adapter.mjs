#!/usr/bin/env node
/**
 * Antigravity CLI の Stop hook 用アダプター。
 *
 * stop-verify.mjs は Claude Code / Codex の契約に従い
 *   成功  → exit 0（stdout 空）
 *   失敗  → {"decision":"block","reason":"..."}
 * を返す。
 *
 * Antigravity CLI の Stop 契約は
 *   続行  → {"decision":"continue","reason":"..."}
 *   停止  → {} または decision が "continue" 以外
 * なので、このアダプターが block → continue へ変換する。
 */
import { spawn } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const scriptPath = resolve(join(__dirname, 'stop-verify.mjs'))

// Antigravity から受け取った stdin をそのまま子プロセスへ渡す
const stdinData = readFileSync(0, 'utf8')

const child = spawn(process.execPath, [scriptPath], {
  stdio: ['pipe', 'pipe', 'pipe'],
})

child.stdin.write(stdinData)
child.stdin.end()

let stdout = ''
let stderr = ''
child.stdout.on('data', (chunk) => {
  stdout += chunk
})
child.stderr.on('data', (chunk) => {
  stderr += chunk
})

child.on('close', (exitCode) => {
  const raw = stdout.trim()
  const errorOutput = stderr.trim()

  // stop-verify.mjs が正常終了かつ出力なし → 検証成功、停止を許可
  if (!raw && exitCode === 0) {
    console.log(JSON.stringify({}))
    process.exit(0)
  }

  // 異常終了 → スクリプト自体の障害。fail-closed で停止をブロック
  if (exitCode !== 0) {
    const detail =
      [errorOutput, raw && `stdout:\n${raw}`].filter(Boolean).join('\n\n') ||
      `Exited with code ${exitCode}.`

    console.log(
      JSON.stringify({
        decision: 'continue',
        reason: `stop-verify.mjs crashed (exit ${exitCode}). Blocking stop.\n${detail}`,
      }),
    )

    return
  }

  try {
    const result = JSON.parse(raw)

    // "block" を Antigravity の "continue"（= 停止をブロック）へ変換
    if (result.decision === 'block') {
      console.log(
        JSON.stringify({
          decision: 'continue',
          reason: result.reason ?? 'Verification failed.',
        }),
      )
    } else {
      // その他の decision はそのまま透過
      console.log(raw)
    }
  } catch {
    // JSON パース失敗時はそのまま出力
    console.log(raw)
  }
})

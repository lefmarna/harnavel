import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import {
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const STOP_HOOK = path.join(ROOT, 'scripts/hooks/stop-verify.mjs')
const LOGGING_PNPM = '#!/bin/sh\nprintf "%s\\n" "$*" >> "$PNPM_LOG"\n'
const IGNORED_FILE_PNPM = [
  '#!/bin/sh',
  'printf "%s\\n" "$*" >> "$PNPM_LOG"',
  'case "$*" in',
  '  *"exec oxlint"*)',
  '    case " $* " in',
  '      *" --no-error-on-unmatched-pattern "*) exit 0 ;;',
  '      *) echo "No files found to lint." >&2; exit 1 ;;',
  '    esac',
  '    ;;',
  '  *) exit 0 ;;',
  'esac',
  '',
].join('\n')

type StopHookFixture = {
  fixture: string
  pnpmLog: string
  repo: string
}

test('Oxlint が無視する生成ファイルだけでも pre-commit を通過する', () => {
  const result = spawnSync(
    'pnpm',
    [
      'exec',
      'lefthook',
      'run',
      'pre-commit',
      '--job',
      'fix staged frontend files with oxlint',
      '--file',
      'resources/js/routes/index.ts',
      '--force',
      '--no-stage-fixed',
      '--no-tty',
    ],
    {
      cwd: ROOT,
      encoding: 'utf8',
    },
  )

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`)
})

test('.oxlintrc.json の変更時は stop hook がリポジトリ全体を lint する', () => {
  const fixture = createStopHookFixture(
    '.oxlintrc.json',
    '{}\n',
    '{"rules": {}}\n',
    LOGGING_PNPM,
  )

  try {
    const result = runStopHook(fixture)

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`)
    assert.match(readFileSync(fixture.pnpmLog, 'utf8'), /^lint:check$/m)
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true })
  }
})

test('Oxlint が無視する生成ファイルだけでも stop hook を通過する', () => {
  const fixture = createStopHookFixture(
    'resources/js/routes/index.ts',
    'export const value = 1\n',
    'export const value = 2\n',
    IGNORED_FILE_PNPM,
  )

  try {
    const result = runStopHook(fixture)

    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`)
    assert.equal(result.stdout.trim(), '')
  } finally {
    rmSync(fixture.fixture, { force: true, recursive: true })
  }
})

function createStopHookFixture(
  relativeFile: string,
  baseline: string,
  changed: string,
  pnpmScript: string,
): StopHookFixture {
  const fixture = mkdtempSync(path.join(os.tmpdir(), 'stop-verify-'))
  const repo = path.join(fixture, 'repo')
  const bin = path.join(fixture, 'bin')
  const pnpmLog = path.join(fixture, 'pnpm.log')
  const target = path.join(repo, relativeFile)

  mkdirSync(path.dirname(target), { recursive: true })
  mkdirSync(bin)
  writeFileSync(target, baseline)
  runGit(repo, ['init', '--quiet'])
  runGit(repo, ['add', relativeFile])
  runGit(repo, [
    '-c',
    'user.name=Test',
    '-c',
    'user.email=test@example.com',
    '-c',
    'commit.gpgsign=false',
    'commit',
    '--quiet',
    '-m',
    'baseline',
  ])
  writeFileSync(target, changed)
  writeFileSync(path.join(bin, 'pnpm'), pnpmScript, { mode: 0o755 })

  return { fixture, pnpmLog, repo }
}

function runStopHook(fixture: StopHookFixture) {
  return spawnSync(process.execPath, [STOP_HOOK], {
    cwd: fixture.repo,
    encoding: 'utf8',
    env: {
      ...process.env,
      PATH: `${path.join(fixture.fixture, 'bin')}${path.delimiter}${process.env.PATH ?? ''}`,
      PNPM_LOG: fixture.pnpmLog,
    },
    input: '{"stop_hook_active":false}',
  })
}

function runGit(cwd: string, args: string[]): void {
  const result = spawnSync('git', args, { cwd, encoding: 'utf8' })

  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`)
}

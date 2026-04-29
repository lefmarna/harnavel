#!/usr/bin/env node
import { spawn, spawnSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'
import { delimiter, extname, join } from 'node:path'

const PRETTIER_EXTS = new Set([
  '.cjs',
  '.css',
  '.html',
  '.js',
  '.jsx',
  '.json',
  '.mjs',
  '.ts',
  '.tsx',
  '.yaml',
  '.yml',
])
const ESLINT_EXTS = new Set(['.cjs', '.js', '.jsx', '.mjs', '.ts', '.tsx'])
const PHP_EXTS = new Set(['.php'])
const TS_EXTS = new Set(['.ts', '.tsx'])
const MD_EXTS = new Set(['.md'])

const payload = JSON.parse(readFileSync(0, 'utf8'))

if (payload.stop_hook_active) {
  process.exit(0)
}

const repo = run(['git', 'rev-parse', '--show-toplevel']).stdout.trim()
const { all, existing } = changedFiles(repo)

if (all.length === 0) {
  process.exit(0)
}

const isBlade = (file) => file.endsWith('.blade.php')

const prettier = byExt(existing, PRETTIER_EXTS)
const eslintAll = byExt(all, ESLINT_EXTS)
const eslintFiles = byExt(existing, ESLINT_EXTS)
const phpAll = byExt(all, PHP_EXTS).filter((file) => !isBlade(file))
const phpExisting = byExt(existing, PHP_EXTS).filter((file) => !isBlade(file))
const hasTs = byExt(all, TS_EXTS).length > 0
const hasMd = byExt(all, MD_EXTS).length > 0
const hasJsTsDeletions = eslintAll.length > eslintFiles.length
const hasPhpDeletions = phpAll.length > phpExisting.length
const hasAnyPhpChange = byExt(all, PHP_EXTS).length > 0
const dockerReady =
  hasAnyPhpChange && hasCommand('docker') && dockerAppRunning(repo)

if (dockerReady) {
  ensureDockerGitSafeDirectory(repo)
}

// ---- Auto-fix phase (sequential, lefthook order) ----

if (dockerReady && phpExisting.length) {
  run(
    [
      'docker',
      'compose',
      'exec',
      '-T',
      'app',
      'vendor/bin/pint',
      '--format=agent',
      ...phpExisting,
    ],
    repo,
  )
}

if (eslintFiles.length && hasCommand('pnpm')) {
  run(['pnpm', 'exec', 'eslint', '--fix', ...eslintFiles], repo)
}

if (prettier.length && hasCommand('pnpm')) {
  run(
    ['pnpm', 'exec', 'prettier', '--write', '--log-level', 'warn', ...prettier],
    repo,
  )
}

// ---- Verify phase (parallel, scoped where possible) ----

const tasks = []

if (eslintAll.length && hasCommand('pnpm')) {
  tasks.push(
    hasJsTsDeletions
      ? runAsync(['pnpm', 'lint:check'], repo, 'ESLint')
      : runAsync(['pnpm', 'exec', 'eslint', ...eslintFiles], repo, 'ESLint'),
  )
}

if (hasTs && hasCommand('pnpm')) {
  tasks.push(runAsync(['pnpm', 'types:check'], repo, 'Types'))
}

if (hasMd && hasCommand('pnpm')) {
  tasks.push(runAsync(['pnpm', 'lint:docs'], repo, 'Markdown links'))
}

if (dockerReady) {
  if (phpAll.length) {
    tasks.push(
      hasPhpDeletions
        ? runAsync(
            ['docker', 'compose', 'exec', '-T', 'app', 'composer', 'analyse'],
            repo,
            'Larastan',
          )
        : runAsync(
            [
              'docker',
              'compose',
              'exec',
              '-T',
              'app',
              'vendor/bin/phpstan',
              'analyse',
              '--memory-limit=1G',
              '--no-progress',
              ...phpExisting,
            ],
            repo,
            'Larastan',
          ),
    )
  }

  tasks.push(
    runAsync(
      [
        'docker',
        'compose',
        'exec',
        '-T',
        'app',
        'php',
        'artisan',
        'test',
        '--compact',
        '--dirty',
      ],
      repo,
      'Pest (--dirty)',
    ),
  )
}

if (
  process.env.STOP_HOOK_E2E === '1' &&
  eslintFiles.length > 0 &&
  hasCommand('pnpm')
) {
  tasks.push(
    runAsync(['pnpm', 'test:e2e', '--reporter=dot'], repo, 'E2E (Playwright)'),
  )
}

const failures = (await Promise.all(tasks)).filter((result) => result !== null)

if (failures.length) {
  const reason = [
    'Verification failed before completion. Fix these issues before completing the task.',
    ...failures.map((f) => `\n[${f.title}]\n${f.output}`),
  ].join('\n')

  console.log(
    JSON.stringify({
      decision: 'block',
      reason,
    }),
  )
}

function changedFiles(repo) {
  const tracked = run(['git', 'diff', '--name-only', 'HEAD'], repo)
  const untracked = run(
    ['git', 'ls-files', '--others', '--exclude-standard'],
    repo,
  )
  const merged = [
    ...tracked.stdout.split('\n'),
    ...untracked.stdout.split('\n'),
  ]
  const all = [...new Set(merged)].filter(Boolean).sort()
  const existing = all.filter((file) => existsSync(join(repo, file)))

  return { all, existing }
}

function byExt(files, extensions) {
  return files.filter((file) => extensions.has(extname(file)))
}

function dockerAppRunning(repo) {
  const result = run(
    ['docker', 'compose', 'ps', '--services', '--filter', 'status=running'],
    repo,
  )

  return result.status === 0 && result.stdout.split('\n').includes('app')
}

function ensureDockerGitSafeDirectory(repo) {
  const result = run(
    [
      'docker',
      'compose',
      'exec',
      '-T',
      'app',
      'git',
      'rev-parse',
      '--show-toplevel',
    ],
    repo,
  )

  if (result.status === 0) {
    return
  }

  const output = `${result.stdout}\n${result.stderr}`

  if (!output.includes('dubious ownership')) {
    return
  }

  const pwd = run(['docker', 'compose', 'exec', '-T', 'app', 'pwd'], repo)
  const safeDirectory = pwd.status === 0 ? pwd.stdout.trim() : '/app'

  run(
    [
      'docker',
      'compose',
      'exec',
      '-T',
      'app',
      'git',
      'config',
      '--global',
      '--add',
      'safe.directory',
      safeDirectory,
    ],
    repo,
  )
}

function hasCommand(command) {
  return (process.env.PATH ?? '')
    .split(delimiter)
    .some((directory) => existsSync(join(directory, command)))
}

function run(command, cwd) {
  return spawnSync(command[0], command.slice(1), {
    cwd,
    encoding: 'utf8',
  })
}

function runAsync(command, cwd, title) {
  return new Promise((resolve) => {
    const child = spawn(command[0], command.slice(1), { cwd })
    let stdout = ''
    let stderr = ''

    child.stdout.on('data', (chunk) => {
      stdout += chunk
    })
    child.stderr.on('data', (chunk) => {
      stderr += chunk
    })
    child.on('error', (error) => {
      resolve({ title, output: `Failed to spawn: ${error.message}` })
    })
    child.on('close', (status) => {
      if (status === 0) {
        resolve(null)

        return
      }

      const combined = `${stdout}\n${stderr}`.trim() || `Exited with ${status}.`
      resolve({ title, output: trim(combined) })
    })
  })
}

function trim(output) {
  const lines = output.split('\n')

  return lines.length <= 40
    ? output
    : [
        ...lines.slice(0, 40),
        `... truncated ${lines.length - 40} lines ...`,
      ].join('\n')
}

#!/usr/bin/env node
import { spawnSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'
import {
  delimiter,
  extname,
  isAbsolute,
  join,
  relative,
  resolve,
} from 'node:path'

const PRETTIER_EXTS = new Set([
  '.css',
  '.js',
  '.jsx',
  '.json',
  '.ts',
  '.tsx',
  '.yaml',
  '.yml',
])
const ESLINT_EXTS = new Set(['.js', '.jsx', '.ts', '.tsx'])
const PHP_EXTS = new Set(['.php'])
const PATCH_FILE_RE = /^\*\*\* (?:Add|Update|Delete) File: (.+)$/

const payload = JSON.parse(readFileSync(0, 'utf8'))
const repo = run(['git', 'rev-parse', '--show-toplevel']).stdout.trim()
const files = changedFiles(payload, repo)
const messages = []

const prettier = byExt(files, PRETTIER_EXTS)
const eslint = byExt(files, ESLINT_EXTS)
const php = byExt(files, PHP_EXTS)

if (prettier.length && hasCommand('pnpm')) {
  collect(
    ['pnpm', 'exec', 'prettier', '--write', '--log-level', 'warn', ...prettier],
    repo,
    messages,
    'Prettier failed',
  )
}

if (eslint.length && hasCommand('pnpm')) {
  collect(
    ['pnpm', 'exec', 'eslint', '--fix', ...eslint],
    repo,
    messages,
    'ESLint found issues',
  )
}

if (php.length && hasCommand('docker') && dockerAppRunning(repo)) {
  collect(
    [
      'docker',
      'compose',
      'exec',
      '-T',
      'app',
      'vendor/bin/pint',
      '--format=agent',
      ...php,
    ],
    repo,
    messages,
    'Pint failed',
  )
}

if (messages.length) {
  console.log(
    JSON.stringify({
      hookSpecificOutput: {
        hookEventName: 'PostToolUse',
        additionalContext: messages.join('\n\n'),
      },
    }),
  )
}

function changedFiles(payload, repo) {
  const input = payload.tool_input ?? {}
  const paths = new Set([input.file_path, input.path])

  for (const line of String(input.command ?? '').split('\n')) {
    const match = line.match(PATCH_FILE_RE)

    if (match) {
      paths.add(match[1])
    }
  }

  const normalized = new Set()

  for (const path of paths) {
    if (typeof path !== 'string' || path === '') {
      continue
    }

    const absolute = isAbsolute(path) ? path : resolve(repo, path)
    const rel = relative(repo, absolute)

    if (!rel || rel.startsWith('..') || isAbsolute(rel)) {
      continue
    }

    if (rel.startsWith('.codex/')) {
      continue
    }

    if (!existsSync(absolute)) {
      continue
    }

    normalized.add(rel)
  }

  return [...normalized].sort()
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

function collect(command, cwd, messages, title) {
  const result = run(command, cwd)

  if (result.status === 0) {
    return
  }

  const output =
    `${result.stdout}\n${result.stderr}`.trim() ||
    `Exited with ${result.status}.`
  messages.push(`${title}:\n${trim(output)}`)
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

function trim(output) {
  const lines = output.split('\n')

  return lines.length <= 60
    ? output
    : [
        ...lines.slice(0, 60),
        `... truncated ${lines.length - 60} lines ...`,
      ].join('\n')
}

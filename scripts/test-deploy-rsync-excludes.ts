import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const WORKFLOW_PATH =
  process.env.DEPLOY_RSYNC_WORKFLOW ??
  path.join(ROOT, '.github/workflows/delivery.yml')

interface ProtectedPath {
  path: string
  reason: string
}

const PROTECTED_PATHS: ProtectedPath[] = [
  { path: '.env', reason: 'production-only Laravel environment file' },
  { path: '.user.ini', reason: 'XServer PHP runtime overrides' },
  {
    path: 'storage/app/private/user-upload.txt',
    reason: 'private user-generated files',
  },
  {
    path: 'storage/app/public/user-upload.txt',
    reason: 'public user-generated files',
  },
  { path: 'storage/logs/laravel.log', reason: 'production logs' },
  {
    path: 'storage/framework/cache/data/production-cache.php',
    reason: 'runtime cache data',
  },
  {
    path: 'storage/framework/sessions/session-id',
    reason: 'runtime session files',
  },
  {
    path: 'storage/framework/views/compiled.php',
    reason: 'runtime compiled views',
  },
  {
    path: 'dev.laravel-app-template.com/index.html',
    reason: 'XServer sibling subdomain directory',
  },
]

function extractDeployRsyncBlock(workflow: string): string {
  const start = workflow.indexOf('- name: Deploy to XServer via Rsync')

  if (start === -1) {
    throw new Error('Deploy to XServer via Rsync step was not found.')
  }

  const nextStep = workflow.indexOf('\n      - name:', start + 1)

  return nextStep === -1
    ? workflow.slice(start)
    : workflow.slice(start, nextStep)
}

function extractExcludes(workflow: string): string[] {
  const block = extractDeployRsyncBlock(workflow)
  const excludes = [...block.matchAll(/--exclude='([^']+)'/g)].map(
    (match) => match[1] ?? '',
  )

  if (excludes.length === 0) {
    throw new Error('No rsync --exclude entries were found in the deploy step.')
  }

  return excludes
}

function writeFixtureFile(root: string, relativePath: string): void {
  const filePath = path.join(root, relativePath)

  fs.mkdirSync(path.dirname(filePath), { recursive: true })
  fs.writeFileSync(filePath, `${relativePath}\n`)
}

function createFixture(): { remote: string; source: string; tmp: string } {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'deploy-rsync-excludes-'))
  const source = path.join(tmp, 'source')
  const remote = path.join(tmp, 'remote')

  fs.mkdirSync(source, { recursive: true })
  fs.mkdirSync(remote, { recursive: true })

  writeFixtureFile(source, 'index.php')
  writeFixtureFile(remote, 'stale-unprotected.txt')

  for (const protectedPath of PROTECTED_PATHS) {
    writeFixtureFile(remote, protectedPath.path)
  }

  return { remote, source, tmp }
}

function runRsyncDryRun(
  excludes: string[],
  source: string,
  remote: string,
): string {
  const args = [
    '-ain',
    '--delete',
    ...excludes.map((exclude) => `--exclude=${exclude}`),
    `${source}/`,
    `${remote}/`,
  ]

  return execFileSync('rsync', args, { encoding: 'utf8' })
}

function assertContainsDeletion(output: string, relativePath: string): void {
  if (!output.includes(relativePath)) {
    throw new Error(
      `Expected rsync --delete dry-run to delete unprotected path: ${relativePath}`,
    )
  }
}

function assertProtected(output: string, protectedPath: ProtectedPath): void {
  if (!output.includes(protectedPath.path)) {
    return
  }

  throw new Error(
    [
      `Protected path appeared in rsync --delete dry-run output: ${protectedPath.path}`,
      `Reason: ${protectedPath.reason}`,
    ].join('\n'),
  )
}

function main(): void {
  const workflow = fs.readFileSync(WORKFLOW_PATH, 'utf8')
  const excludes = extractExcludes(workflow)
  const { remote, source, tmp } = createFixture()

  try {
    const output = runRsyncDryRun(excludes, source, remote)

    assertContainsDeletion(output, 'stale-unprotected.txt')

    for (const protectedPath of PROTECTED_PATHS) {
      assertProtected(output, protectedPath)
    }

    console.log(
      `OK: ${PROTECTED_PATHS.length} remote-protected rsync paths survive --delete dry-run.`,
    )
  } finally {
    fs.rmSync(tmp, { force: true, recursive: true })
  }
}

main()

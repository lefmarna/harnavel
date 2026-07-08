import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const TEST_ROOT = path.join(ROOT, 'resources/js')
const TEST_FILE_RE = /\.test\.tsx?$/

function findUnitTests(dir: string): string[] {
  if (!fs.existsSync(dir)) {
    return []
  }

  const files: string[] = []

  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const absolutePath = path.join(dir, entry.name)

    if (entry.isDirectory()) {
      files.push(...findUnitTests(absolutePath))
      continue
    }

    if (!entry.isFile() || !TEST_FILE_RE.test(entry.name)) {
      continue
    }

    const segments = path.relative(TEST_ROOT, absolutePath).split(path.sep)

    if (segments.includes('__tests__')) {
      files.push(absolutePath)
    }
  }

  return files.sort()
}

function main(): void {
  const testFiles = findUnitTests(TEST_ROOT)

  if (testFiles.length === 0) {
    console.log(
      'No frontend unit tests found at resources/js/**/__tests__/**/*.test.{ts,tsx}; skipping.',
    )

    return
  }

  const result = spawnSync(
    process.execPath,
    ['--import', 'tsx', '--test', ...testFiles],
    {
      stdio: 'inherit',
    },
  )

  if (result.error !== undefined) {
    throw result.error
  }

  process.exit(result.status ?? 1)
}

main()

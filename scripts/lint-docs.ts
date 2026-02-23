/**
 * Validates internal markdown links in docs/ directory.
 * Checks that relative links resolve to actual files.
 */
import fs from 'node:fs'
import { glob } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const DOCS_DIR = path.join(ROOT, 'docs')

const LINK_RE = /\[([^\]]*)\]\(([^)]+)\)/g
const IGNORED_PREFIXES = ['http://', 'https://', 'mailto:', '#']
// Review gate reports contain quoted links in JSON that may reference old paths
const REPORT_RE = /^\d{8}-\d{4}_summary\.md$/

interface LinkError {
  file: string
  line: number
  link: string
  reason: string
}

async function findMarkdownFiles(dir: string): Promise<string[]> {
  const files: string[] = []
  for await (const entry of glob('**/*.md', { cwd: dir })) {
    if (REPORT_RE.test(path.basename(entry))) continue
    files.push(path.join(dir, entry))
  }
  // Also check root AGENTS.md and CLAUDE.md
  for (const name of ['AGENTS.md', 'CLAUDE.md']) {
    const p = path.join(ROOT, name)
    if (fs.existsSync(p)) files.push(p)
  }
  return files.sort()
}

function checkLinks(filePath: string, content: string): LinkError[] {
  const errors: LinkError[] = []
  const lines = content.split('\n')
  const fileDir = path.dirname(filePath)

  for (let i = 0; i < lines.length; i++) {
    let match: RegExpExecArray | null
    LINK_RE.lastIndex = 0
    while ((match = LINK_RE.exec(lines[i])) !== null) {
      const rawLink = match[2]

      // Strip anchor
      const linkWithoutAnchor = rawLink.split('#')[0]
      if (!linkWithoutAnchor) continue // pure anchor link
      if (IGNORED_PREFIXES.some((p) => rawLink.startsWith(p))) continue

      const resolved = path.resolve(fileDir, linkWithoutAnchor)

      if (!fs.existsSync(resolved)) {
        errors.push({
          file: path.relative(ROOT, filePath),
          line: i + 1,
          link: rawLink,
          reason: `Target not found: ${path.relative(ROOT, resolved)}`,
        })
      }
    }
  }
  return errors
}

async function main() {
  const files = await findMarkdownFiles(DOCS_DIR)
  const allErrors: LinkError[] = []

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf-8')
    allErrors.push(...checkLinks(file, content))
  }

  if (allErrors.length === 0) {
    console.log(
      `✓ ${files.length} markdown files checked, no broken links found.`,
    )
    process.exit(0)
  }

  console.error(`✗ Found ${allErrors.length} broken link(s):\n`)
  for (const err of allErrors) {
    console.error(`  ${err.file}:${err.line} → ${err.link}`)
    console.error(`    ${err.reason}\n`)
  }
  process.exit(1)
}

main()

// Validates internal markdown links in repository markdown files.
// Checks that relative links resolve to actual files.
//
// Additional checks:
// - CLAUDE.md / GEMINI.md are one-line @AGENTS.md pointers.
// - .claude/skills/<name>/SKILL.md and .agents/skills/<name>/SKILL.md frontmatter
//   has `name` matching directory name and non-empty `description`.
// - Repo-owned skills listed in MIRRORED_SKILLS have the same shared files in
//   .claude/skills and .agents/skills after agent-specific metadata is excluded
//   and SKILL.md frontmatter is normalized.
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

interface LintError {
  file: string
  reason: string
}

async function findMarkdownFiles(dir: string): Promise<string[]> {
  const files: string[] = []

  for await (const entry of glob('**/*.md', { cwd: dir })) {
    if (REPORT_RE.test(path.basename(entry))) {
      continue
    }

    files.push(path.join(dir, entry))
  }

  // Also check key root markdown files that act as agent entry points.
  for (const name of [
    'AGENTS.md',
    'CLAUDE.md',
    'GEMINI.md',
    'CONTEXT.md',
    'README.md',
  ]) {
    const p = path.join(ROOT, name)

    if (fs.existsSync(p)) {
      files.push(p)
    }
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

      if (!linkWithoutAnchor) {
        continue
      } // pure anchor link

      if (IGNORED_PREFIXES.some((p) => rawLink.startsWith(p))) {
        continue
      }

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

// ---------------------------------------------------------------------------
// Pointer file validation
// CLAUDE.md and GEMINI.md must be exactly one-line @AGENTS.md pointers.
// ---------------------------------------------------------------------------

function checkPointerFiles(): LintError[] {
  const errors: LintError[] = []
  const pointerFiles = ['CLAUDE.md', 'GEMINI.md']

  for (const name of pointerFiles) {
    const filePath = path.join(ROOT, name)

    if (!fs.existsSync(filePath)) {
      continue
    }

    const content = fs.readFileSync(filePath, 'utf-8')
    // Allow an optional trailing newline; the substantive content must be "@AGENTS.md" only.
    const trimmed = content.trim()

    if (trimmed !== '@AGENTS.md') {
      errors.push({
        file: name,
        reason: `Must contain exactly "@AGENTS.md" (one-line pointer). Found: ${JSON.stringify(trimmed.slice(0, 80))}`,
      })
    }
  }

  return errors
}

// ---------------------------------------------------------------------------
// Skill frontmatter validation
// Each SKILL.md must have:
//   - `name` matching the parent directory name
//   - non-empty `description`
// ---------------------------------------------------------------------------

interface FrontmatterParseResult {
  name?: string
  description?: string
}

function parseFrontmatter(content: string): FrontmatterParseResult | null {
  if (!content.startsWith('---')) {
    return null
  }

  const end = content.indexOf('\n---', 3)

  if (end === -1) {
    return null
  }

  const block = content.slice(3, end)
  const result: FrontmatterParseResult = {}

  for (const line of block.split('\n')) {
    // Match `key: value` or `key: 'value'` or `key: "value"`
    const m = line.match(/^(\w[\w-]*):\s*(.+)$/)

    if (!m) {
      continue
    }

    const key = m[1]
    let value = m[2].trim()

    // Strip surrounding quotes
    if (
      (value.startsWith("'") && value.endsWith("'")) ||
      (value.startsWith('"') && value.endsWith('"'))
    ) {
      value = value.slice(1, -1)
    }

    if (key === 'name') {
      result.name = value
    } else if (key === 'description') {
      result.description = value
    }
  }

  return result
}

async function checkSkillFrontmatter(): Promise<LintError[]> {
  const errors: LintError[] = []
  const skillDirs = [
    path.join(ROOT, '.claude', 'skills'),
    path.join(ROOT, '.agents', 'skills'),
  ]

  for (const skillsRoot of skillDirs) {
    if (!fs.existsSync(skillsRoot)) {
      continue
    }

    const entries = fs.readdirSync(skillsRoot, { withFileTypes: true })

    for (const entry of entries) {
      if (!entry.isDirectory()) {
        continue
      }

      const dirName = entry.name
      const skillFile = path.join(skillsRoot, dirName, 'SKILL.md')

      if (!fs.existsSync(skillFile)) {
        continue
      }

      const relPath = path.relative(ROOT, skillFile)
      const content = fs.readFileSync(skillFile, 'utf-8')
      const fm = parseFrontmatter(content)

      if (fm === null) {
        errors.push({
          file: relPath,
          reason:
            'Missing YAML frontmatter (expected --- block at top of file)',
        })
        continue
      }

      if (!fm.name) {
        errors.push({
          file: relPath,
          reason: 'Frontmatter missing required field: `name`',
        })
      } else if (fm.name !== dirName) {
        errors.push({
          file: relPath,
          reason: `Frontmatter \`name\` ("${fm.name}") does not match directory name ("${dirName}")`,
        })
      }

      if (!fm.description || fm.description.trim() === '') {
        errors.push({
          file: relPath,
          reason: 'Frontmatter `description` is empty or missing',
        })
      }
    }
  }

  return errors
}

// ---------------------------------------------------------------------------
// Skill duplication sync check
//
// Only repo-owned skills that are intentionally mirrored to both
// .claude/skills and .agents/skills are checked for content equivalence.
//
// External skills (kiro-*, etc.) are deliberately maintained in different
// formats per agent toolchain (.claude adds allowed-tools/argument-hint
// frontmatter; .agents uses <background_information>/<instructions> tags).
// Including them would produce permanent false positives and bury real drift.
//
// Which repo-owned skills get mirrored:
//   Mirror a skill when any agent doing ordinary implementation work in this
//   repository needs it (adding a page, compacting ADRs, updating packages).
//   Keep a skill in .claude only when it drives Claude-specific repository
//   operations that other toolchains do not run (template convergence and
//   project bootstrapping: absorb-from-apps, merge-template, project-docs-init).
//   AGENTS.md points non-Claude agents at those by path when they need them.
//
// Mirroring means duplicated files, not symlinks: the two trees carry different
// runtime metadata that a link would destroy (.claude SKILL.md has
// disable-model-invocation / argument-hint; .agents has agents/openai.yaml).
// That divergence is the reason this checker normalizes before comparing.
//
// When a repo-owned skill is placed in both directories, add its name here.
// ---------------------------------------------------------------------------

const MIRRORED_SKILLS: readonly string[] = [
  // repo-owned skills mirrored to both .claude/skills and .agents/skills.
  // Add a skill here when its SKILL.md body and shared frontmatter are intentionally identical in both trees.
  'adr-compact',
  'add-page',
  'update-laravel-packages',
]

const AGENT_SPECIFIC_FRONTMATTER_KEYS = new Set([
  'argument-hint',
  'disable-model-invocation',
])

function normalizeMirroredSkillContent(content: string): string {
  if (!content.startsWith('---')) {
    return content
  }

  const end = content.indexOf('\n---', 3)

  if (end === -1) {
    return content
  }

  const block = content.slice(3, end)
  const filteredBlock = block
    .split('\n')
    .filter((line) => {
      const match = line.match(/^(\w[\w-]*):/)

      return match === null || !AGENT_SPECIFIC_FRONTMATTER_KEYS.has(match[1])
    })
    .join('\n')

  return `---${filteredBlock}${content.slice(end)}`
}

function listMirroredSkillFiles(skillDir: string): string[] {
  const files: string[] = []

  function visit(currentDir: string, relativeDir = ''): void {
    for (const entry of fs.readdirSync(currentDir, { withFileTypes: true })) {
      const relativePath = path.join(relativeDir, entry.name)

      // Agent-specific UI and invocation metadata intentionally differs.
      if (
        relativePath === 'agents' ||
        relativePath.startsWith(`agents${path.sep}`)
      ) {
        continue
      }

      const absolutePath = path.join(currentDir, entry.name)

      if (entry.isDirectory()) {
        visit(absolutePath, relativePath)
      } else if (entry.isFile()) {
        files.push(relativePath)
      }
    }
  }

  visit(skillDir)

  return files.sort()
}

function checkSkillSync(): LintError[] {
  const errors: LintError[] = []
  const claudeSkillsRoot = path.join(ROOT, '.claude', 'skills')
  const agentsSkillsRoot = path.join(ROOT, '.agents', 'skills')

  for (const name of MIRRORED_SKILLS) {
    const claudeDir = path.join(claudeSkillsRoot, name)
    const agentsDir = path.join(agentsSkillsRoot, name)

    if (!fs.existsSync(claudeDir)) {
      errors.push({
        file: `.claude/skills/${name}`,
        reason: `Mirrored skill "${name}" is missing from .claude/skills. Add it or remove from MIRRORED_SKILLS.`,
      })
      continue
    }

    if (!fs.existsSync(agentsDir)) {
      errors.push({
        file: `.agents/skills/${name}`,
        reason: `Mirrored skill "${name}" is missing from .agents/skills. Add it or remove from MIRRORED_SKILLS.`,
      })
      continue
    }

    const claudeFiles = listMirroredSkillFiles(claudeDir)
    const agentsFiles = listMirroredSkillFiles(agentsDir)
    const claudeFileSet = new Set(claudeFiles)
    const agentsFileSet = new Set(agentsFiles)
    const allFiles = [...new Set([...claudeFiles, ...agentsFiles])].sort()

    if (!claudeFileSet.has('SKILL.md')) {
      errors.push({
        file: `.claude/skills/${name}/SKILL.md`,
        reason: `Mirrored skill "${name}" is missing required file "SKILL.md" in .claude/skills.`,
      })
    }

    if (!agentsFileSet.has('SKILL.md')) {
      errors.push({
        file: `.agents/skills/${name}/SKILL.md`,
        reason: `Mirrored skill "${name}" is missing required file "SKILL.md" in .agents/skills.`,
      })
    }

    for (const relativeFile of allFiles) {
      if (!claudeFileSet.has(relativeFile)) {
        errors.push({
          file: `.claude/skills/${name}/${relativeFile}`,
          reason: `Mirrored skill "${name}" is missing shared file "${relativeFile}" in .claude/skills.`,
        })
        continue
      }

      if (!agentsFileSet.has(relativeFile)) {
        errors.push({
          file: `.agents/skills/${name}/${relativeFile}`,
          reason: `Mirrored skill "${name}" is missing shared file "${relativeFile}" in .agents/skills.`,
        })
        continue
      }

      const claudeFile = path.join(claudeDir, relativeFile)
      const agentsFile = path.join(agentsDir, relativeFile)
      const contentsMatch =
        relativeFile === 'SKILL.md'
          ? normalizeMirroredSkillContent(
              fs.readFileSync(claudeFile, 'utf-8'),
            ) ===
            normalizeMirroredSkillContent(fs.readFileSync(agentsFile, 'utf-8'))
          : fs.readFileSync(claudeFile).equals(fs.readFileSync(agentsFile))

      if (!contentsMatch) {
        errors.push({
          file: `.claude/skills/${name}/${relativeFile}`,
          reason: `Mirrored skill "${name}" shared file "${relativeFile}" has diverged between .claude/skills and .agents/skills.`,
        })
      }
    }
  }

  return errors
}

async function main() {
  const files = await findMarkdownFiles(DOCS_DIR)
  const allLinkErrors: LinkError[] = []

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf-8')
    allLinkErrors.push(...checkLinks(file, content))
  }

  const pointerErrors = checkPointerFiles()
  const frontmatterErrors = await checkSkillFrontmatter()
  const syncErrors = checkSkillSync()

  const hasErrors =
    allLinkErrors.length > 0 ||
    pointerErrors.length > 0 ||
    frontmatterErrors.length > 0 ||
    syncErrors.length > 0

  if (!hasErrors) {
    console.log(
      `✓ ${files.length} markdown files checked, no broken links found.`,
    )
    console.log('✓ Pointer files (CLAUDE.md, GEMINI.md) are valid.')
    console.log(
      '✓ Skill frontmatter valid in .claude/skills and .agents/skills.',
    )
    console.log(
      `✓ Mirrored skills (${MIRRORED_SKILLS.join(', ')}) are content-equivalent.`,
    )
    process.exit(0)
  }

  if (allLinkErrors.length > 0) {
    console.error(`✗ Found ${allLinkErrors.length} broken link(s):\n`)

    for (const err of allLinkErrors) {
      console.error(`  ${err.file}:${err.line} → ${err.link}`)
      console.error(`    ${err.reason}\n`)
    }
  }

  if (pointerErrors.length > 0) {
    console.error(`✗ Found ${pointerErrors.length} pointer file error(s):\n`)

    for (const err of pointerErrors) {
      console.error(`  ${err.file}`)
      console.error(`    ${err.reason}\n`)
    }
  }

  if (frontmatterErrors.length > 0) {
    console.error(
      `✗ Found ${frontmatterErrors.length} skill frontmatter error(s):\n`,
    )

    for (const err of frontmatterErrors) {
      console.error(`  ${err.file}`)
      console.error(`    ${err.reason}\n`)
    }
  }

  if (syncErrors.length > 0) {
    console.error(`✗ Found ${syncErrors.length} skill sync error(s):\n`)

    for (const err of syncErrors) {
      console.error(`  ${err.file}`)
      console.error(`    ${err.reason}\n`)
    }
  }

  process.exit(1)
}

main()

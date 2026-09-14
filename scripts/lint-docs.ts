import fs from "node:fs";
import { glob } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, "..");
const DOCS_DIR = path.join(ROOT, "docs");

const LINK_RE = /\[([^\]]*)\]\(([^)]+)\)/g;
const IGNORED_PREFIXES = ["http://", "https://", "mailto:", "#"];

interface LinkError {
  file: string;
  line: number;
  link: string;
  reason: string;
}

interface LintError {
  file: string;
  reason: string;
}

async function findMarkdownFiles(dir: string): Promise<string[]> {
  const files: string[] = [];

  for await (const entry of glob("**/*.md", { cwd: dir })) {
    files.push(path.join(dir, entry));
  }

  for (const name of ["AGENTS.md", "CLAUDE.md", "GEMINI.md", "CONTEXT.md", "README.md"]) {
    const p = path.join(ROOT, name);

    if (fs.existsSync(p)) {
      files.push(p);
    }
  }

  return files.sort();
}

function checkLinks(filePath: string, content: string): LinkError[] {
  const errors: LinkError[] = [];
  const lines = content.split("\n");
  const fileDir = path.dirname(filePath);

  for (const [index, line] of lines.entries()) {
    let match: RegExpExecArray | null;
    LINK_RE.lastIndex = 0;

    while ((match = LINK_RE.exec(line)) !== null) {
      const rawLink = match[2];

      if (rawLink === undefined) {
        continue;
      }

      const linkWithoutAnchor = rawLink.split("#")[0];

      if (!linkWithoutAnchor) {
        continue;
      }

      if (IGNORED_PREFIXES.some((p) => rawLink.startsWith(p))) {
        continue;
      }

      const resolved = path.resolve(fileDir, linkWithoutAnchor);

      if (!fs.existsSync(resolved)) {
        errors.push({
          file: path.relative(ROOT, filePath),
          line: index + 1,
          link: rawLink,
          reason: `Target not found: ${path.relative(ROOT, resolved)}`,
        });
      }
    }
  }

  return errors;
}

function checkPointerFiles(): LintError[] {
  const errors: LintError[] = [];
  const pointerFiles = ["CLAUDE.md", "GEMINI.md"];

  for (const name of pointerFiles) {
    const filePath = path.join(ROOT, name);

    if (!fs.existsSync(filePath)) {
      continue;
    }

    const content = fs.readFileSync(filePath, "utf-8");
    const trimmed = content.trim();

    if (trimmed !== "@AGENTS.md") {
      errors.push({
        file: name,
        reason: `Must contain exactly "@AGENTS.md" (one-line pointer). Found: ${JSON.stringify(trimmed.slice(0, 80))}`,
      });
    }
  }

  return errors;
}

interface FrontmatterParseResult {
  name?: string;
  description?: string;
}

function parseFrontmatter(content: string): FrontmatterParseResult | null {
  if (!content.startsWith("---")) {
    return null;
  }

  const end = content.indexOf("\n---", 3);

  if (end === -1) {
    return null;
  }

  const block = content.slice(3, end);
  const result: FrontmatterParseResult = {};

  for (const line of block.split("\n")) {
    const m = line.match(/^(\w[\w-]*):\s*(.+)$/);

    if (!m) {
      continue;
    }

    const key = m[1];
    const rawValue = m[2];

    if (key === undefined || rawValue === undefined) {
      continue;
    }

    let value = rawValue.trim();

    if (
      (value.startsWith("'") && value.endsWith("'")) ||
      (value.startsWith('"') && value.endsWith('"'))
    ) {
      value = value.slice(1, -1);
    }

    if (key === "name") {
      result.name = value;
    } else if (key === "description") {
      result.description = value;
    }
  }

  return result;
}

async function checkSkillFrontmatter(): Promise<LintError[]> {
  const errors: LintError[] = [];
  const skillDirs = [path.join(ROOT, ".claude", "skills"), path.join(ROOT, ".agents", "skills")];

  for (const skillsRoot of skillDirs) {
    if (!fs.existsSync(skillsRoot)) {
      continue;
    }

    const entries = fs.readdirSync(skillsRoot, { withFileTypes: true });

    for (const entry of entries) {
      if (!entry.isDirectory()) {
        continue;
      }

      const dirName = entry.name;
      const skillFile = path.join(skillsRoot, dirName, "SKILL.md");
      const relPath = path.relative(ROOT, skillFile);

      if (!fs.existsSync(skillFile)) {
        errors.push({
          file: relPath,
          reason: "Skill directory is missing SKILL.md",
        });
        continue;
      }

      const content = fs.readFileSync(skillFile, "utf-8");
      const fm = parseFrontmatter(content);

      if (fm === null) {
        errors.push({
          file: relPath,
          reason: "Missing YAML frontmatter (expected --- block at top of file)",
        });
        continue;
      }

      if (!fm.name) {
        errors.push({
          file: relPath,
          reason: "Frontmatter missing required field: `name`",
        });
      } else if (fm.name !== dirName) {
        errors.push({
          file: relPath,
          reason: `Frontmatter \`name\` ("${fm.name}") does not match directory name ("${dirName}")`,
        });
      }

      if (!fm.description || fm.description.trim() === "") {
        errors.push({
          file: relPath,
          reason: "Frontmatter `description` is empty or missing",
        });
      }
    }
  }

  return errors;
}

const REPO_OWNED_SKILLS: readonly string[] = [
  "absorb-from-apps",
  "adr-compact",
  "merge-template",
  "project-docs-init",
  "runtime-investigate",
  "update-laravel-packages",
];

function expectedPointerBody(title: string, canonical: string): string {
  return [
    `# ${title}`,
    "",
    `Read \`${canonical}\` and follow it. That file is the canonical body of this skill, shared`,
    "with every agent toolchain that works this repository. Skill-local paths it gives, such as",
    "`references/`, resolve next to that file, not next to this one.",
    "",
    "This file exists only to register the skill with the Claude-specific frontmatter above, which",
    "a symlink would destroy. It carries no instructions of its own — never add any here, or the",
    "two copies diverge again.",
  ].join("\n");
}

function bodyOf(content: string): string {
  if (!content.startsWith("---")) {
    return content;
  }

  const end = content.indexOf("\n---", 3);

  return end === -1 ? content : content.slice(end + 4);
}

function normalize(body: string): string {
  return body
    .replace(/\r\n/g, "\n")
    .split("\n")
    .map((line) => line.replace(/\s+$/, ""))
    .join("\n")
    .trim();
}

function checkSkillPointers(): LintError[] {
  const errors: LintError[] = [];

  for (const name of REPO_OWNED_SKILLS) {
    // Markdown のパス区切りは OS に関係なく / を使う。
    const canonical = `.agents/skills/${name}/SKILL.md`;
    const pointer = `.claude/skills/${name}/SKILL.md`;
    const canonicalPath = path.join(ROOT, ".agents", "skills", name, "SKILL.md");
    const pointerPath = path.join(ROOT, ".claude", "skills", name, "SKILL.md");

    if (!fs.existsSync(canonicalPath)) {
      errors.push({
        file: canonical,
        reason: `Canonical body for repo-owned skill "${name}" is missing. Add it or remove the name from REPO_OWNED_SKILLS.`,
      });
      continue;
    }

    if (!fs.existsSync(pointerPath)) {
      errors.push({
        file: pointer,
        reason: `Claude pointer for repo-owned skill "${name}" is missing. Add it or remove the name from REPO_OWNED_SKILLS.`,
      });
      continue;
    }

    const title = /^#\s+(.+)$/m.exec(bodyOf(fs.readFileSync(canonicalPath, "utf-8")))?.[1];

    if (title === undefined) {
      errors.push({
        file: canonical,
        reason: "Canonical body must open with a `# <title>` heading; the pointer reuses it.",
      });
      continue;
    }

    const expected = expectedPointerBody(title.trim(), canonical);
    const actual = normalize(bodyOf(fs.readFileSync(pointerPath, "utf-8")));

    if (actual !== normalize(expected)) {
      errors.push({
        file: pointer,
        reason: `Body must match the pointer template exactly. Replace everything below the frontmatter with:\n\n${expected
          .split("\n")
          .map((line) => `      ${line}`)
          .join("\n")}`,
      });
    }
  }

  return errors;
}

async function main() {
  const files = await findMarkdownFiles(DOCS_DIR);
  const allLinkErrors: LinkError[] = [];

  for (const file of files) {
    const content = fs.readFileSync(file, "utf-8");
    allLinkErrors.push(...checkLinks(file, content));
  }

  const pointerErrors = checkPointerFiles();
  const frontmatterErrors = await checkSkillFrontmatter();
  const pointerSkillErrors = checkSkillPointers();

  const hasErrors =
    allLinkErrors.length > 0 ||
    pointerErrors.length > 0 ||
    frontmatterErrors.length > 0 ||
    pointerSkillErrors.length > 0;

  if (!hasErrors) {
    console.log(`✓ ${files.length} markdown files checked, no broken links found.`);
    console.log("✓ Pointer files (CLAUDE.md, GEMINI.md) are valid.");
    console.log("✓ Skill frontmatter valid in .claude/skills and .agents/skills.");
    console.log(
      `✓ Repo-owned skills (${REPO_OWNED_SKILLS.join(", ")}) point at their canonical body.`,
    );
    process.exit(0);
  }

  if (allLinkErrors.length > 0) {
    console.error(`✗ Found ${allLinkErrors.length} broken link(s):\n`);

    for (const err of allLinkErrors) {
      console.error(`  ${err.file}:${err.line} → ${err.link}`);
      console.error(`    ${err.reason}\n`);
    }
  }

  if (pointerErrors.length > 0) {
    console.error(`✗ Found ${pointerErrors.length} pointer file error(s):\n`);

    for (const err of pointerErrors) {
      console.error(`  ${err.file}`);
      console.error(`    ${err.reason}\n`);
    }
  }

  if (frontmatterErrors.length > 0) {
    console.error(`✗ Found ${frontmatterErrors.length} skill frontmatter error(s):\n`);

    for (const err of frontmatterErrors) {
      console.error(`  ${err.file}`);
      console.error(`    ${err.reason}\n`);
    }
  }

  if (pointerSkillErrors.length > 0) {
    console.error(`✗ Found ${pointerSkillErrors.length} repo-owned skill pointer error(s):\n`);

    for (const err of pointerSkillErrors) {
      console.error(`  ${err.file}`);
      console.error(`    ${err.reason}\n`);
    }
  }

  process.exit(1);
}

await main();

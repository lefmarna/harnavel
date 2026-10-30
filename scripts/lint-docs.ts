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

async function findMarkdownFiles(dir: string): Promise<string[]> {
  const files: string[] = [];

  for await (const entry of glob("**/*.md", { cwd: dir })) {
    files.push(path.join(dir, entry));
  }

  for (const name of ["AGENTS.md", "GLOSSARY.md", "GLOSSARY-MAP.md", "README.md"]) {
    const p = path.join(ROOT, name);

    if (fs.existsSync(p)) {
      files.push(p);
    }
  }

  return files.toSorted();
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

async function main() {
  const files = await findMarkdownFiles(DOCS_DIR);
  const errors: LinkError[] = [];

  for (const file of files) {
    errors.push(...checkLinks(file, fs.readFileSync(file, "utf-8")));
  }

  if (errors.length === 0) {
    console.log(`✓ ${files.length} markdown files checked, no broken links found.`);
    process.exit(0);
  }

  console.error(`✗ Found ${errors.length} broken link(s):\n`);

  for (const err of errors) {
    console.error(`  ${err.file}:${err.line} → ${err.link}`);
    console.error(`    ${err.reason}\n`);
  }

  process.exit(1);
}

await main();

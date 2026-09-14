---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary merge or conflict work.
---

# Merge Template

Resolve `git pull <remote> <branch>` conflicts by applying TADR-0003.

## Ownership

- **Shared:** template-origin framework, auth/settings foundations, test infrastructure,
  architecture rules, CI actions, reusable workflows, template ADRs, and testing policy. Prefer the
  template.
- **Hybrid:** `AGENTS.md`, `delivery.yml`, `mise.toml`, `phpstan.neon.dist`, architecture tests, and
  files containing both shared scaffolding and app behavior. Take template-origin changes and keep
  app-specific content.
- **App-owned:** domain behavior, presentation, branding, environment values, `CONTEXT.md`, flat App
  ADRs, and app-specific routes or deployment layout. Preserve the app.

Special cases:

- A template change that restructures migrations (folding `add_`/`make_` migrations into their
  create migration, or dropping a create/drop pair) arrives as resolved deletions, not conflicts, so
  it never appears in step 3. An app whose production database cannot be rebuilt must restore its own
  applied migrations from `git status` before committing; otherwise migrations the live schema
  depends on disappear from the repository.
- Never take template lockfiles over app lockfiles. When manifests change, run unscoped
  `composer update` and `pnpm update` for the merged app; package-scoped updates can retain stale
  transitive dependencies.
- Keep app-specific `delivery.yml` rsync excludes, release-critical files, environment secrets, and
  extra deploy steps while aligning the shared Laravel maintenance and `/up` health flow.
- Keep `parseModelCastsMethod: true`; preserve app-specific PHPStan paths and ignores.
- Mutation uses the shared blocking score of 100%, but each app owns the classes named by
  `mutates()`. Do not remove an app's old selection until its own allowlist has been built and
  measured.
- Preserve the app's dedicated E2E database. Do not restore E2E-only environment files or HTTP
  helper routes.
- Keep `e2e/global-setup.ts` shared. Apps needing process setup should point Playwright at an
  app-owned wrapper that calls its default export.
- Preserve flat App ADRs and the app-owned index in `docs/adr/README.md`; take Template ADRs from the
  template.
- Translation keys travel with their consumer. When an app replaced or dropped the screen, request,
  or mailable a template key serves, do not take the key: an unreferenced key is copy nobody renders
  and, in a bilingual app, a parity test enforces its translation forever.
- Vendored skills under `.claude/skills/` and `.agents/skills/` are shared. Each path stays
  byte-identical to the same path in the template. The two trees are not copies of each other —
  toolchain-specific frontmatter, pointer bodies, and `agents/openai.yaml` sidecars legitimately
  differ — so never reconcile one mirror against the other. Never rewrite their content while
  resolving; take git's result verbatim. This applies to files git merged cleanly, not only to
  conflicted ones.

## Workflow

1. Resolve `REMOTE` and `BRANCH` from the invocation, defaulting to `template` and `main`, then
   inspect `git status`, remotes, the current branch, and `MERGE_HEAD`.
2. Pull the resolved remote and branch when they have not already been pulled.
3. List unresolved paths with `git diff --diff-filter=U --name-only`.
4. Read both sides and nearby tests before resolving each conflict.
5. Classify each hunk as shared, hybrid, or app-owned and remove dead code introduced by the
   resolution.
6. Regenerate lockfiles and Wayfinder output when their inputs changed.
7. List app paths that the template once deleted and still omits:

   ```bash
   template_ref="${REMOTE:-template}/${BRANCH:-main}"
   comm -12 \
     <(comm -23 \
       <(git log --diff-filter=D --name-only --pretty=format: "$template_ref" -- | sed '/^$/d' | sort -u) \
       <(git ls-tree -r --name-only "$template_ref" | sort -u)) \
     <(git ls-files | sort -u)
   ```

   Check each candidate's template history and current consumers before deleting a template
   leftover or preserving an app-owned path.
8. Confirm no conflict markers remain:

   ```bash
   git grep -nI -E '^(<<<<<<<|=======|>>>>>>>)' -- . \
     ':(exclude)vendor/**' \
     ':(exclude)node_modules/**' \
     ':(exclude)public/build/**' \
     ':(exclude)storage/**' \
     ':(exclude)docs/generated/**'
   ```

9. Confirm the resolution left no whitespace-only drift in shared vendored paths. Files that git
   merged cleanly never appear in step 3, so this is the only check that catches content rewritten
   outside conflict resolution:

   ```bash
   template_ref="${REMOTE:-template}/${BRANCH:-main}"
   comm -23 \
     <(git diff --name-only "$template_ref" -- .claude/skills .agents/skills | sort -u) \
     <(git diff --name-only --ignore-all-space "$template_ref" -- .claude/skills .agents/skills |
       sort -u)
   ```

   Any output is a shared file the resolution rewrote. Restore it with
   `git checkout "$template_ref" -- <path>`.

10. Run relevant checks from `AGENTS.md`, starting narrowly.
11. Stage resolved files, review the staged diff, and stop before committing.

Report template choices, preserved app choices, deleted or consolidated tests, verification
results, and any improvement that should move upstream. Prefer the safer side for security and
personal data, and move shared tests only with the implementation they specify.

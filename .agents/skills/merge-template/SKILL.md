---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary merge or conflict work.
---

# Merge Template

Integrate `git pull <remote> <branch>` changes and resolve conflicts by applying TADR-0003.

Read [Dependency reconciliation](references/dependency-reconciliation.md) before pulling or
resuming an existing merge. It defines how to preserve the app's dependency baseline and apply
required template changes without coupling the merge to routine package maintenance.

## Ownership

- **Shared:** template-origin framework, auth/settings foundations, test infrastructure,
  architecture rules, CI actions, reusable workflows, template ADRs, and testing policy. Prefer the
  template.
- **Hybrid:** `AGENTS.md`, `delivery.yml`, `mise.toml`, `phpstan.neon.dist`, `renovate.json`,
  architecture tests, and files containing both shared scaffolding and app behavior. Take
  template-origin changes and keep app-specific content.
- **App-owned:** domain behavior, presentation, branding, environment values, `CONTEXT.md`, flat App
  ADRs, and app-specific routes or deployment layout. Preserve the app.

Special cases:

- Shared specs reach app pages only through calibration points: `home` in `config/fortify.php`,
  `HOME_URL`, `PROTECTED_PATH`, and `SETTINGS_PATHS` in `e2e/support/auth.ts`, and
  `getProtectedPage()` and `settingsUrl()` in `tests/Pest.php`. Keep the app's values, and assert
  through them (`config('fortify.home')`, never a literal route).
- The welcome and dashboard pages, `resources/js/navigation.ts`, the logo, and copy values in
  `lang/*/pages.php` are placeholders the app replaces; shared specs read page copy through
  `pageCopy()`. Keep the `home` route name and the names of state-changing auth and settings
  routes, which shared specs call.

- A template change that restructures migrations (folding `add_`/`make_` migrations into their
  create migration, or dropping a create/drop pair) arrives as resolved deletions, not conflicts, so
  it never appears in step 3. An app whose production database cannot be rebuilt must restore its own
  applied migrations from `git status` before committing; otherwise migrations the live schema
  depends on disappear from the repository.
- Dependency declarations are hybrid: align shared requirements and retain app-specific consumers
  and constraints. Lockfiles are app-owned, including when git merges them without conflicts. Use
  the pre-pull app lockfiles to reconcile merged dependency inputs with minimal changes; do not
  adopt template lockfiles or run routine full updates as part of a merge. Required shared fixes
  and toolchain migrations follow the dependency reconciliation reference.
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
   inspect `git status`, remotes, the current branch, and `MERGE_HEAD`. Preserve or recover the
   pre-pull app dependency baseline before changing lockfiles.
2. Pull the resolved remote and branch with `git pull --no-commit --no-ff` when they have not
   already been pulled, so a conflict-free merge still stops before committing.
3. List unresolved paths with `git diff --diff-filter=U --name-only`.
4. Read both sides and nearby tests before resolving each conflict.
5. Classify each hunk as shared, hybrid, or app-owned and remove dead code introduced by the
   resolution.
6. Reconcile dependency inputs and required shared fixes using the dependency reference, checking
   cleanly merged paths too. Regenerate Wayfinder output when its inputs changed.
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
11. Stage resolved files, review the staged diff, and stop before committing. Resolve a deletion
    by removing the file with `rm` and staging the path with `git add`; never use `git rm`.

Report template choices, preserved app choices, deleted or consolidated tests, verification
results, dependency changes and their reasons, audit findings and maintenance follow-ups, and any
improvement that should move upstream. Prefer the safer side for security and personal data, and
move shared tests only with the implementation they specify.

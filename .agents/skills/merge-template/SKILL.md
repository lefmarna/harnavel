---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules. Use only when the user explicitly invokes this skill; never activate implicitly for ordinary merge or conflict work.
---

# Merge Template

Integrate `git pull <remote> <branch>` changes and resolve conflicts by applying
[TADR-0003](../../../docs/adr/template/0003-template-ownership.md). Read
[Dependency reconciliation](references/dependency-reconciliation.md) before pulling or resuming a
merge.

## Ownership

- **Shared** (prefer the template): template-origin framework, auth/settings foundations, test
  infrastructure, architecture rules, CI actions, reusable workflows, Template ADRs, testing policy,
  and the vendored skills in `.claude/skills/` and `.agents/skills/`.
- **Hybrid** (take template-origin changes, keep app content): `README.md`, `AGENTS.md`,
  `delivery.yml`, `mise.toml`, `phpstan.neon.dist`, `renovate.json`, dependency manifests,
  architecture tests, and other files mixing shared scaffolding with app behavior.
- **App-owned** (preserve the app): domain behavior, presentation, branding, environment values,
  lockfiles, `GLOSSARY.md`, flat App ADRs with the App ADRs index in `docs/adr/README.md`, and
  app-specific routes or deployment layout.

Special cases:

- **README**: the shared baseline is the latest `.agents/skills/project-docs-init/assets/README.md`,
  not Harnavel's root README. Keep the pre-pull bodies of the `app:overview`, `app:development`, and
  `app:notices` blocks and take all other text from the scaffold, even when git merged README cleanly
  or only the scaffold changed. A legacy README without markers gets them now: move its product
  description, features, usage, environment values, extra commands, app docs links, and notices
  into the matching blocks, with each marker pair exactly once and no placeholders left. App text
  later found outside the blocks moves into them rather than being dropped; compare with the
  pre-pull scaffold to tell local additions from shared text being updated.
- **Calibration points**: shared specs reach app pages only through `home` in `config/fortify.php`,
  `HOME_URL`, `PROTECTED_PATH`, and `SETTINGS_PATHS` in `e2e/support/auth.ts`, and
  `getProtectedPage()` and `settingsUrl()` in `tests/Pest.php`, and `replaceSiteDescription()` in
  `tests/Feature/SeoMetaContractTest.php`. Keep the app's values, and assert through them
  (`config('fortify.home')`, never a literal route).
- **Placeholders**: the welcome and dashboard pages, `resources/js/navigation.ts`, the logo, and
  copy in `lang/*/pages.php` are the app's to replace; shared specs read copy through `pageCopy()`.
  Keep the `home` route name and the names of state-changing auth and settings routes shared specs
  call.
- **Migrations**: a template change that folds or drops migrations arrives as resolved deletions,
  not conflicts. An app whose production database cannot be rebuilt restores its applied migrations
  from `git status` before committing.
- **Dependencies**: align shared requirements, keep app-specific consumers and constraints, and
  reconcile lockfiles from the pre-pull app lockfiles per the dependency reference, including when
  git merged them cleanly. Template lockfiles and routine full updates are not part of a merge.
- **Delivery and analysis**: keep the app's `delivery.yml` rsync excludes, release-critical files,
  secrets, and extra deploy steps while aligning the shared maintenance and `/up` health flow. Keep
  `parseModelCastsMethod: true` with the app's PHPStan paths and ignores, and the app's `mutates()`
  selections under the shared 100% score.
- **E2E**: keep the app's dedicated E2E database; deleted E2E-only environment files and HTTP helper
  routes stay deleted. `e2e/global-setup.ts` stays shared; an app needing process setup points
  Playwright at an app-owned wrapper that calls its default export.
- **Translation keys** travel with their consumer: skip a template key whose screen, request, or
  mailable the app replaced or dropped, since in a bilingual app a parity test would enforce
  translating copy nobody renders.
- **Vendored skills**: each path stays byte-identical to the same path in the template, merged
  cleanly or not; take git's result verbatim. The two trees legitimately differ (frontmatter,
  pointer bodies, `agents/openai.yaml` sidecars), so never reconcile one against the other.

## Workflow

1. Resolve `REMOTE` (default `template`) and `BRANCH` (default `main`). Inspect `git status`,
   remotes, branch, and `MERGE_HEAD`, and preserve or recover the pre-pull README and dependency
   baselines.
2. Run `git pull --no-commit --no-ff <remote> <branch>` unless already pulled, so a conflict-free
   merge still stops before committing.
3. For each path in `git diff --diff-filter=U --name-only`, read both sides and nearby tests,
   classify each hunk as shared, hybrid, or app-owned, and remove dead code the resolution leaves.
4. Reconcile dependency inputs and required shared fixes per the dependency reference and README
   against the scaffold, checking cleanly merged paths too. Regenerate Wayfinder output when its
   inputs changed.
5. List app paths the template once deleted and still omits, then delete each template leftover or
   keep each app-owned path after checking its template history and current consumers:

   ```bash
   template_ref="${REMOTE:-template}/${BRANCH:-main}"
   comm -12 \
     <(comm -23 \
       <(git log --diff-filter=D --name-only --pretty=format: "$template_ref" -- | sed '/^$/d' | sort -u) \
       <(git ls-tree -r --name-only "$template_ref" | sort -u)) \
     <(git ls-files | sort -u)
   ```

6. Confirm no conflict markers remain, and that no vendored skill differs from the template only in
   whitespace (cleanly merged files never reach step 3, so this is the only check for them); restore
   any listed path with `git checkout "$template_ref" -- <path>`:

   ```bash
   git grep -nI -E '^(<<<<<<<|=======|>>>>>>>)' -- . \
     ':(exclude)vendor/**' ':(exclude)node_modules/**' ':(exclude)public/build/**' \
     ':(exclude)storage/**' ':(exclude)docs/generated/**'

   template_ref="${REMOTE:-template}/${BRANCH:-main}"
   comm -23 \
     <(git diff --name-only "$template_ref" -- .claude/skills .agents/skills | sort -u) \
     <(git diff --name-only --ignore-all-space "$template_ref" -- .claude/skills .agents/skills | sort -u)
   ```

7. Run relevant checks from `AGENTS.md`, starting narrowly. Stage resolved files, resolving a
   deletion with `rm` and `git add` (never `git rm`), review the staged diff, and stop before
   committing.

Report template choices, preserved app choices, deleted or consolidated tests, verification results,
dependency changes and their reasons, audit findings and maintenance follow-ups, and improvements
that should move upstream. Prefer the safer side for security and personal data, and move shared
tests only with the implementation they specify.

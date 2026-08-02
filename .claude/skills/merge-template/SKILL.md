---
name: merge-template
description: Resolve conflicts from pulling the template repository according to the app-specific versus template-aligned decision rules.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [BRANCH=main]"
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

- Never take template lockfiles over app lockfiles. Merge manifests, then regenerate
  `composer.lock` and `pnpm-lock.yaml`.
- Keep app-specific `delivery.yml` rsync excludes, release-critical files, environment secrets, and
  extra deploy steps while aligning the shared Laravel maintenance and `/up` health flow.
- Keep `parseModelCastsMethod: true`; preserve app-specific PHPStan paths and ignores.
- Mutation uses the shared blocking score of 100%, but each app owns the classes named by
  `mutates()`. Do not remove an app's old selection until its own allowlist has been built and
  measured.
- Preserve the app's `E2E_PORT` and dedicated E2E database. Do not restore E2E-only environment
  files or HTTP helper routes.
- Preserve flat App ADRs and the app-owned index in `docs/adr/README.md`; take Template ADRs from the
  template.

## Workflow

1. Inspect `git status`, remotes, the current branch, and `MERGE_HEAD`.
2. Pull the template when it has not already been pulled.
3. List unresolved paths with `git diff --diff-filter=U --name-only`.
4. Read both sides and nearby tests before resolving each conflict.
5. Classify each hunk as shared, hybrid, or app-owned and remove dead code introduced by the
   resolution.
6. Regenerate lockfiles and Wayfinder output when their inputs changed.
7. Review app-only paths in shared locations against template history. Remove leftovers from
   template deletions and preserve genuinely app-owned paths.
8. Confirm no conflict markers remain:

   ```bash
   git grep -nI -E '^(<<<<<<<|=======|>>>>>>>)' -- . \
     ':(exclude)vendor/**' \
     ':(exclude)node_modules/**' \
     ':(exclude)public/build/**' \
     ':(exclude)storage/**' \
     ':(exclude)docs/generated/**'
   ```

9. Run the checks required by `AGENTS.md`, starting narrowly.
10. Stage resolved files, review the staged diff, and stop before committing.

Report template choices, preserved app choices, deleted or consolidated tests, verification
results, and any improvement that should move upstream. Prefer the safer side for security and
personal data, and move shared tests only with the implementation they specify.

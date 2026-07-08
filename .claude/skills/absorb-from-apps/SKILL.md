---
name: absorb-from-apps
description: Absorb the best app-grown changes back into the template and keep future downstream pulls conflict-light.
disable-model-invocation: true
argument-hint: "[REMOTE=template] [APPS=<app1> <app2> ...]"
---

# Absorb from Apps

This is the upstream counterpart to `/merge-template` (template -> app downstream). It pulls app-grown changes that are not app-specific back into the template, chooses the best shared version, aligns the shared surface, and keeps future `git pull template main` runs judgment-free except for calibration-line conflicts.

The decision model is [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md) (shared-surface convergence). This command is the operational procedure and must be run in the template repository.

## Principles

Use [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md) for the classification model, and use the operational path classification below for concrete paths.

- **Convergence uses three tiers** (TADR-0003):
  - **T1 Shared**: files with no app-specific content. Aim for byte identity.
  - **T2 Hybrid**: template scaffolding and app content coexist. Converge only template-origin hunks and leave app extensions in place. These files do not become byte-identical.
  - **T3 App-owned**: presentation, branding, domain, and deploy settings. Do not converge.
- **Leave app-priority content app-side**: calibration values (coverage/mutation `--min`, whether mutation is blocking, and `@pest-mutate-ignore` annotation density), domain logic, design tokens, i18n, app-specific routes/domains/deploy settings, `CONTEXT.md`, and App ADRs (`docs/adr/NNNN-*.md`). Do not move these upstream.
- **Choose by best practice, not majority vote**. Select the best version hunk by hunk.
- **Eliminate equivalent mutations by simplification before annotation**. Use `@pest-mutate-ignore` with a reason only when simplification is not possible and the code is load-bearing. See `docs/testing-as-spec.md`.
- **Move tests and implementation together**. More comprehensive shared tests should move upstream only when the matching implementation is also present in the template. Do not upstream specs that cannot be green in the template.
- **Keep PRs small, verified, and uncommitted**. Split by file kind, verify each PR, and stop before commit so the user can review and commit.
- **Do not commit working logs**. Per-app checklists and concrete diff notes are transient working material and should stay in `/tmp` or similar locations.

## Operational Path Classification

There is no legacy manifest. The concrete classification used for convergence lives in this procedure.

- **T1 Shared**: `tests/TestCase.php`, `tests/Feature/Architecture/**` (`tests/Feature/Architecture/ArchTest.php` excluded), `app/Concerns/PasswordValidationRules.php`, `e2e/support/**`, `e2e/tsconfig.json`, `playwright.config.ts`, `scripts/lint-docs.ts`, `scripts/run-unit-tests.ts`, `scripts/tsconfig.json`, `scripts/hooks/**`, `.github/workflows/reusable-test.yml`, `.github/workflows/mutation.yml`, `.github/actions/**`, `docs/testing-as-spec.md`, `docs/adr/template/**`, `.claude/skills/absorb-from-apps/**`, `.claude/skills/merge-template/**`, `.claude/skills/add-page/**`, `.claude/skills/adr-compact/**`, `.agents/skills/add-page/**`, `.agents/skills/adr-compact/**`
- **T2 Hybrid**: `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `app/Http/Controllers/Settings/**`, `app/Http/Controllers/Auth/GoogleAuthController.php`, `app/Http/Requests/Settings/**`, `app/Repositories/User/EloquentUserRepository.php`, `app/Services/GoogleAuth/**`, `app/ViewModels/**`, `tests/Pest.php`, `tests/Feature/Auth/**`, `tests/Feature/Settings/**`, `tests/Feature/Architecture/ArchTest.php`, `resources/js/components/ui/**`, `scripts/test-deploy-rsync-excludes.ts`, `.github/workflows/check.yml`, `.github/workflows/quality.yml`, `.github/workflows/delivery.yml`, `docs/adr/README.md`, `mise.toml`
- **T3 App-owned**: `resources/js/pages/**`, `resources/js/components/**` (`resources/js/components/ui/**` excluded), `resources/css/**`, `public/**`, `routes/**`, `app/Http/Controllers/**` (Settings / GoogleAuth excluded), `composer.lock`, `pnpm-lock.yaml`, `.env.example`, `.env`, `README.md`, `CONTEXT.md`, `docs/adr/[0-9][0-9][0-9][0-9]-*.md`
- **Calibration lines**: `--min`, `continue-on-error`, their immediately adjacent reason comments in `reusable-test.yml` / `mutation.yml`, and `@pest-mutate-ignore` annotation density are app-priority.

## Arguments

- `REMOTE`: Remote name that points to the template in each app repository. Default: `template`.
- `APPS`: Derived app repository paths, separated by spaces. If omitted, ask the user because the template repository cannot infer them.

## Workflow

### 1. Confirm Preconditions

Resolve the target app paths (`APPS`). If unknown, ask the user. For each app:

- Always confirm that the worktree is clean. If there is uncommitted WIP, the later `git diff <REMOTE>/main` worktree comparison will mix drift with WIP and may accidentally absorb temporary work into the template. If dirty, stop and ask the user to commit or stash. Also confirm that the app is on the branch intended as the upstream source.
- If clean, fetch the latest template remote.

```bash
dirty=0
for d in <APPS>; do
  st=$(git -C "$d" status --porcelain)
  if [ -n "$st" ]; then
    echo "[fail] $d: dirty worktree; commit or stash before rerunning"
    echo "$st"
    dirty=1
    continue
  fi
  git -C "$d" fetch -q "$REMOTE"
  echo "[ok] $d: clean / $(git -C "$d" rev-parse --abbrev-ref HEAD) @ $REMOTE/main=$(git -C "$d" rev-parse --short "$REMOTE/main")"
done
if [ "$dirty" -ne 0 ]; then exit 1; fi
```

### 2. Inventory Drift

Each app's `git diff <REMOTE>/main` is the authoritative inventory of shared files that will conflict on the next pull, assuming step 1 confirmed each app is clean and all state is committed. Uncommitted WIP would be mixed into this worktree comparison, so the clean check must come first.

```bash
# Every file tracked by the template repository is the base shared surface.
git ls-files > /tmp/tpl.txt
for d in <APPS>; do
  echo "## $d"
  git -C "$d" diff "$REMOTE/main" --name-only | sort > /tmp/app.txt
  comm -12 <(sort /tmp/tpl.txt) /tmp/app.txt
done
```

A consensus scan is also useful: at least two apps are byte-identical to each other and differ from the template, which suggests the template may be behind. Watch for false positives:

- Changes already moved upstream by you mean the app should follow downstream, not become an upstream source.
- Apps that are simply old should pull downstream instead of being treated as upstream candidates.
- Determine drift direction from git history or content before acting.

### 3. Classify T1 / T2 / T3

Classify every changed file and hunk into the three tiers. Exclude T3 and app-specific hunks.

### 4. Choose the Best Version Hunk by Hunk

- Choose the best generic improvement or annotation. Simplifying away equivalent mutations is better than annotating them.
- When multiple apps fixed the same area differently, choose one canonical version. The others will converge downstream.
- Do not upstream app-specific hunks such as custom Params/ViewModels, domain columns, i18n, or design.

### 5. Move Changes Upstream to the Template

- Keep PRs small by file kind. For T1 files, write the exact bytes from the best-version app when byte identity is the goal. For T2 files, converge only template-origin hunks.
- Verify according to the changed area in each PR: `mise run verify:backend`, `pnpm format:check && pnpm lint:check && pnpm types:check`, scoped mutation if needed (`docker compose exec -T app vendor/bin/pest --mutate --covered-only --class="..."`), `pnpm lint:docs`, or YAML validation for workflows.
- Do not commit. Stop after implementation and verification so the user can review and commit.

### 6. Hand Off Downstream

After the template is pushed, each app should run `git pull <REMOTE> main` and `/merge-template` to converge. Prepare transient per-app notes listing app-specific content to keep, such as domain, i18n, design, and calibration lines. Pass those notes to `/merge-template`, but do not commit them.

### 7. Verify Convergence

After each app pull/merge, run `git diff <REMOTE>/main` again and confirm:

- Converged T1 files are byte-identical, and shared hunks in T2 files are gone.
- Remaining drift is calibration lines or app-specific content only.
- No conflict markers remain:

```bash
git -C "$d" grep -nI -E '<<<<<<<|>>>>>>>' -- . ':(exclude)vendor/**' ':(exclude)node_modules/**'
```

## Example

```bash
# Absorb from four derived apps.
/absorb-from-apps APPS="../app-one ../app-two ../app-three ../app-four"
```

## Related

- Downstream (template -> app): [`merge-template`](../merge-template/SKILL.md)
- Decision model: [`TADR-0003`](../../../docs/adr/template/0003-shared-surface-convergence.md)
- Testing policy and equivalent mutation catalog: `docs/testing-as-spec.md`

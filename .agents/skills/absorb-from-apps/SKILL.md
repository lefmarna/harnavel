---
name: absorb-from-apps
description: Absorb the best app-grown changes back into the template and keep future downstream pulls conflict-light. Run it in the template repository. Use only when the user explicitly invokes this skill.
---

# Absorb from Apps

Upstream counterpart to [`merge-template`](../merge-template/SKILL.md), governed by
[`TADR-0003`](../../../docs/adr/template/0003-template-ownership.md). Run it in the template
repository to pull app-grown generic improvements back, choose the best shared version, and leave
future `git pull template main` runs judgment-free except for calibration-line conflicts.

## Arguments

- `REMOTE`: template remote name in each app repository (default: `template`).
- `APPS`: space-separated derived app repository paths (ask the user if omitted).

## Workflow

### 1. Confirm Preconditions

For each path in `APPS`, verify the worktree is clean and on the intended branch, then fetch
`REMOTE`. Stop if any app is dirty so WIP is never mixed into drift comparisons:

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

Compare each app against `<REMOTE>/main` across files tracked by the template:

```bash
git ls-files > /tmp/tpl.txt
for d in <APPS>; do
  echo "## $d"
  git -C "$d" diff "$REMOTE/main" --name-only | sort > /tmp/app.txt
  comm -12 <(sort /tmp/tpl.txt) /tmp/app.txt
done
```

Two or more apps byte-identical to each other but different from the template suggest the
template is behind. Determine drift direction from history or content before acting:

- An app that is simply old, or that still lacks a change you already moved upstream, pulls
  downstream instead of becoming a source.
- Diffs that disappear under `git diff --ignore-all-space` are formatting drift from a merge
  resolution: restore that app to the template version, and check the other apps for the same
  rewrite.

### 3. Classify and Select Hunks

Apply the [`merge-template`](../merge-template/SKILL.md) Ownership rules hunk by hunk, choosing by
best practice, not majority vote:

- **Shared**: write the exact bytes of the best version across apps. When apps fixed the same area
  differently, pick one canonical version; the others converge downstream.
- **Hybrid**: upstream only template-origin hunks.
- **App-owned**: leave app-side (coverage floors, `mutates()` class lists, domain logic, custom
  Params/ViewModels and columns, design tokens, i18n, app routes and deploy settings,
  `GLOSSARY.md`, and App ADRs).
- Move shared tests only with the implementation they specify, so every upstreamed spec is green in
  the template. Add deterministic, observable logic to the mutation selection at 100%, simplifying
  equivalent mutations instead of upstreaming mutation-ignore annotations.

### 4. Apply and Verify in the Template

- Group changes into small, reviewable batches by file kind.
- Run relevant checks (`mise run verify:backend`, `pnpm format:check && pnpm lint:check && pnpm types:check`,
  `mise run mutate`, `pnpm lint:docs`).
- Keep working notes in `/tmp` only. Stop before committing so the user can review and commit.

### 5. Hand Off and Verify Convergence

After the template is pushed, each app runs `git pull <REMOTE> main` and `/merge-template`, given
transient per-app notes of the domain, i18n, design, and calibration content to keep (never
committed). Re-run `git diff <REMOTE>/main` in each app to confirm Shared files are byte-identical,
remaining drift is app-specific or calibration-only, and no conflict markers remain:

```bash
git -C "$d" grep -nI -E '<<<<<<<|>>>>>>>' -- . ':(exclude)vendor/**' ':(exclude)node_modules/**'
```

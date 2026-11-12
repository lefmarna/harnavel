# Branches and CI

`main` is Production and `develop` is the Develop environment for the next release. Each Worktree
branches from `develop` (or `main` in apps without `develop`) and merges through a pull request.
Push freely and open a draft pull request early: checks start when it is marked ready and rerun on
each later push, while Vite+ commit hooks cover the gap locally. Repositories connected to
[CircleCI](circleci.md) run pull request E2E and mutation testing there.

## How delivery avoids retesting

GitHub Free cannot enforce required checks on private repositories, so pull request checks are
advisory and `delivery` is the only gate before a deployment. Parallel Worktrees move `develop`
between a pull request's run and its merge, and every merge method rewrites the SHA, so what carries
over is the tree: a successful run uploads the assets it tested under its tree's name, and
`delivery` deploys that build without retesting when the pushed commit has the same tree. Because
any branch can upload an artifact under any name, `delivery` also checks which run produced it. This
guards against mistakes, not collaborators, who could push to `develop` directly anyway.

A release pull request whose merge result has the same tree as `develop` skips `quality`, because
`delivery` already verified that tree before deploying `develop`.

## Mutation testing and releases

Mutation testing never gates a deployment, and a full run grows with the codebase. It runs on pull
requests into `main`; pull requests into `develop` rely on local `mise run mutate --min=100`. Keep
the release pull request a draft until releasing, because a ready one reruns `mutation` on every
push to `develop`, and never enable automatic head-branch deletion, which would delete `develop` on
the release merge.

## Dependency updates

Mend Renovate Community Cloud opens routine update pull requests. Updates that would rewrite a
declaration wait on the Dependency Dashboard, because shared constraints belong to
`update-laravel-packages`. GitHub Free cannot auto-merge in private repositories, so Renovate
merges a pull request itself once every reported check passes; a check that never starts, such as a
missing CircleCI E2E run, does not hold the merge, and `delivery` still requires the E2E proof or
reruns it. Apps with `develop` add `"baseBranchPatterns": ["develop"]`, so updates reach Production
through the release pull request. Renovate reads its configuration from the default branch, so an
app's change to it takes effect at release.

Lock file maintenance refreshes indirect dependencies only when approved, because it skips the
release-age wait and moves 0.x packages within their ranges. pnpm keeps its default one-day release
age, because a longer one also rejects lockfiles holding a newer vulnerability fix.

## Vulnerability fixes

`security` ([TADR-0018](adr/template/0018-audit-driven-security-fixes.md)) fixes vulnerable
dependencies on `main` and `develop` and keeps one issue per branch for what needs a person. A
protected branch rejects its fast-forward, so merge `security-fix/<branch>` through a pull request.
A release carrying an advisory only `develop` has stops at `delivery`, so resolve the `develop`
issue first. Fix the cause rather than closing the issue: the next scan closes it.

Dependabot alerts stay enabled as an independent notice, and Dependabot security updates stay off.

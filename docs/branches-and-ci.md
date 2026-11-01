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

Mend Renovate Community Cloud opens the update pull requests. Updates that would rewrite a
declaration wait on the Dependency Dashboard, because shared constraints belong to
`update-laravel-packages`; a vulnerability fix to the lowest fixed version is that skill's standing
exception. GitHub Free cannot auto-merge in private repositories, so Renovate merges a pull request
itself once every reported check passes; a check that never starts, such as a missing CircleCI E2E
run, does not hold the merge, and `delivery` still requires the E2E proof or reruns it. Dependabot
Alerts stay enabled as a vulnerability source.

Apps with `develop` add `"baseBranchPatterns": ["develop", "main"]` and a rule disabling `main`, so
routine updates reach Production through the release pull request while vulnerability fixes also
open against `main`. `vulnerabilityAlerts.enabled` is explicit because only forced settings
override that rule. If the release pull request then conflicts on a lockfile, merge `main` into
`develop` and keep `develop`'s lockfile once its own fix has merged. Renovate reads its
configuration from the default branch, so an app's change to it takes effect at release.

Renovate cannot target indirect Composer or pnpm dependencies. Lock file maintenance refreshes them
only when approved, because it skips the release-age wait and moves 0.x packages within their
ranges; until then, `audit` fails every pull request on an indirect advisory until an override or a
parent update resolves it. pnpm keeps its default one-day release age, because a longer one also
rejects lockfiles holding a newer vulnerability fix.

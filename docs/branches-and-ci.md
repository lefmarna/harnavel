# Branches and CI

`main` is Production and `develop` is the Develop environment for the next release. Neither is a
working branch: each Worktree works on its own branch and merges through a pull request. Apps
without `develop` open pull requests against `main`.

Pushing a branch is a backup, so push freely and open a draft pull request early. Checks start when
it is marked ready and rerun on each later push; lefthook covers the gap locally. Repositories
connected to [CircleCI](circleci.md) run pull request E2E and mutation testing there.

## How delivery avoids retesting

GitHub Free cannot enforce required checks on private repositories, so pull request checks are
advisory and `delivery` is the only gate before a deployment. A passing pull request proves nothing
about the pushed commit by itself: parallel Worktrees move `develop` between a pull request's run
and its merge, and every merge method rewrites the SHA.

What carries over is the tree. A successful run uploads the assets it tested under the name of its
tree, and when the pushed commit has the same tree, `delivery` deploys that build without rerunning
lint and tests. Because any branch can upload an artifact under any name, `delivery` reuses only a push or
manual run of a commit with the deployed tree, or a pull request run of the final head of the pull
request this push merged, unless its base was ever changed. This guards against mistakes, not
against collaborators: anyone who can forge the artifact can also push to `develop` directly.

A release pull request whose merge result has the same tree as `develop` skips `quality`, because
`delivery` already verified that tree before deploying `develop`.

## Mutation testing

Mutation testing never gates a deployment, and a full run grows with the codebase. It runs on pull
requests into `main`; pull requests into `develop` rely on `mise run mutate --min=100` locally.

## Releases

Open the release pull request when releasing, or keep it as a draft until then, because a ready one
reruns `mutation` on every push to `develop`. Do not enable automatic head-branch deletion: merging a
release pull request would delete `develop`.

## Dependency updates

Renovate, through Mend Renovate Community Cloud, opens the update pull requests. Updates that would
rewrite a declaration wait for approval on the Dependency Dashboard, because changing shared
constraints stays with the `update-laravel-packages` skill; a vulnerability fix may still move one
to the lowest fixed version, the standing exception that skill records. Dependabot Alerts stay
enabled as one of its vulnerability sources. GitHub Free cannot auto-merge in private repositories,
so Renovate merges a pull request itself once every check reported on it has passed. A check that
never starts, such as a missing CircleCI E2E run, does not hold the merge; `delivery` still requires
the E2E proof or reruns it before deploying.

Apps with `develop` add `"baseBranchPatterns": ["develop", "main"]` and a rule disabling `main`.
Routine updates then reach Production through the release pull request, while vulnerability fixes
also open against `main` and deploy without waiting for a release. `vulnerabilityAlerts.enabled` is
explicit because only forced settings override that rule. If the release pull request then
conflicts on a lockfile, merge `main` into `develop` and keep `develop`'s lockfile once its own fix
has merged. Renovate reads its configuration from the default branch, so a change to it in an app
takes effect at release.

Renovate cannot target indirect Composer or pnpm dependencies. Lock file maintenance refreshes them,
but it skips the release-age wait and moves 0.x packages within their ranges, so it runs only when
approved. Until then, Dependabot Alerts report an indirect advisory, and `audit` fails every pull
request until an override or a parent update resolves it. pnpm keeps its default one-day release
age, because a longer one also rejects lockfiles holding a newer vulnerability fix.

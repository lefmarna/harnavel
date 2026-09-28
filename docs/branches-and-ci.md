# Branches and CI

`main` is Production and `develop` is the Develop environment for the next release. Neither is a
working branch: each Worktree works on its own feature or fix branch and merges through a pull
request. Apps without `develop` open pull requests against `main`.

## What runs

| Event                                              | Workflows                                           |
| -------------------------------------------------- | --------------------------------------------------- |
| Push to a feature or fix branch                    | None                                                |
| Draft pull request                                 | `quality` and `mutation` start, but skip every job  |
| Ready pull request into `develop` or `main`        | `quality` (lint, Pest, E2E) and `mutation`          |
| Ready release pull request (`develop` into `main`) | `mutation`; `quality` only if merging changes files |
| Push to `develop` or `main`                        | `delivery` (lint, Pest, E2E, then deploy)           |
| Manual dispatch                                    | `quality` or `mutation` on the chosen branch        |

Skipped jobs use no minutes. `mutation` does not start for pull requests that change only
documentation, agent configuration, E2E specs, or frontend sources.

Pushing a branch is a backup, so push freely and open a draft pull request early. Checks start when
it is marked ready and rerun on each later push; lefthook covers the gap locally. Run
`gh workflow run quality --ref <branch>` to check a branch without a pull request.

## Why checks repeat

GitHub Free cannot enforce required checks on private repositories, so pull request checks are
advisory and `delivery` is the only gate before a deployment. It retests the pushed commit because
parallel Worktrees move `develop` between a pull request's run and its merge.

A release pull request whose merge result has the same tree as `develop` would retest exactly what
`delivery` tested on `develop`; that run appears in the pull request's checks, so `quality` skips.
When `main` holds changes `develop` lacks, such as an unmerged hotfix, `quality` tests the merge
result before it reaches `main`. `mutation` always runs once against the integrated result because
no deployment runs it.

## Releases

Open the release pull request when releasing, or keep it as a draft until then. A ready one reruns
`mutation` on every push to `develop`.

Do not enable automatic head-branch deletion: merging a release pull request would delete
`develop`. Delete merged feature branches by hand.

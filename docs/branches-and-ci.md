# Branches and CI

`main` is Production and `develop` is the Develop environment for the next release. Neither is a
working branch: each Worktree works on its own feature or fix branch and merges through a pull
request. Apps without `develop` open pull requests against `main`.

## What runs

| Event                                              | Workflows                                                                |
| -------------------------------------------------- | ------------------------------------------------------------------------ |
| Push to a feature or fix branch                    | None                                                                     |
| Draft pull request                                 | The workflows below start, but skip every job                            |
| Ready pull request into `develop`                  | `quality` (lint, Pest, E2E)                                              |
| Ready pull request into `main`                     | `quality` and `mutation`                                                 |
| Ready release pull request (`develop` into `main`) | `mutation`; `quality` only if merging changes files                      |
| Push to `develop` or `main`                        | `delivery`: lint, Pest, and E2E unless the tree is verified, then deploy |
| Manual dispatch                                    | `quality` or `mutation` on the chosen branch                             |

Skipped jobs use no minutes. `mutation` does not start for pull requests that change only
documentation, agent configuration, E2E specs, or frontend sources.

Repositories connected to [CircleCI](circleci.md) run pull request E2E and `mutation` there
instead; `delivery` and manual dispatches are unchanged.

Pushing a branch is a backup, so push freely and open a draft pull request early. Checks start when
it is marked ready and rerun on each later push; lefthook covers the gap locally. Run
`gh workflow run quality --ref <branch>` to check a branch without a pull request.

## How delivery avoids retesting

GitHub Free cannot enforce required checks on private repositories, so pull request checks are
advisory and `delivery` is the only gate before a deployment. A passing pull request therefore
proves nothing about the pushed commit by itself: parallel Worktrees move `develop` between a
pull request's run and its merge, and every merge method rewrites the SHA.

What carries over is the tree. A successful `quality` or `delivery` run uploads the assets it
tested as `build-<tree>`, where the tree is that of the commit it checked out; for a pull request,
that is GitHub's merge of the head into the base. When the pushed commit has the same tree, the
repository content is identical, so `delivery` deploys that build and skips lint and tests.
Otherwise, including after another pull request merged first, it runs them itself.

A pull request build whose E2E ran on CircleCI is named `build-<tree>-needs-e2e`, and `delivery`
reuses it only together with CircleCI's record of E2E passing on the same tree.

The artifact name comes from the workflow that ran, so any branch can upload one under any name.
`delivery` therefore reuses only a push or manual run of a commit with the deployed tree, or a pull
request run of the final head of the pull request this push merged, unless its base was ever
changed. A `delivery` run that reuses a build uploads it again as `build-<tree>`, so a release
whose merge result matches `develop` reuses the build deployed to `develop`.

This guards against mistakes, not against collaborators: another pull request with the same head
and a modified workflow can still forge the artifact. Anyone who can do that can also push to
`develop` directly.

The build is kept for 3 days. A later merge or release reruns the checks in `delivery`. Artifacts
count toward the account's Actions storage.

A release pull request whose merge result has the same tree as `develop` would retest exactly what
`delivery` verified before deploying `develop`, so `quality` skips. When `main` holds changes
`develop` lacks, such as an unmerged hotfix, `quality` tests the merge result before it reaches
`main`.

## Mutation testing

Mutation testing never gates a deployment, and a full run grows with the codebase. It runs on pull
requests into `main`: release pull requests, or every pull request in apps without `develop`. Pull
requests into `develop` rely on `mise run mutate --min=100` locally; dispatch `mutation` on the
branch when an independent run is worth it.

## Releases

Open the release pull request when releasing, or keep it as a draft until then. A ready one reruns
`mutation` on every push to `develop`.

Do not enable automatic head-branch deletion: merging a release pull request would delete
`develop`. Delete merged feature branches by hand.

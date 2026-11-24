# Pull request E2E and mutation testing on CircleCI

GitHub Free's 2,000 private-repository minutes are shared across the account and dominated by pull
request E2E. Opted-in repositories therefore run pull request E2E and mutation testing on
CircleCI's free plan while lint, Pest, builds, and deployments stay on GitHub Actions. CircleCI was
chosen over Cloud Build because its free plan stops instead of billing when credits run out, skips
draft pull requests natively, and keeps setup short with service containers.

Because GitHub Free cannot require checks on private repositories, `delivery` remains the only gate
and trusts CircleCI E2E only via a tree-keyed record in Cloud Storage written and read with OIDC
through Workload Identity Federation (avoiding CircleCI's full-account API token). An unproven tree
falls back to E2E on GitHub Actions so exhausted credits never block deployment.

Trade-offs: E2E and deployed assets come from separate builds of the same tree; CircleCI and a
billing-linked Google Cloud project add maintenance; and base drift after a PR check forces
`delivery` to rerun everything on GitHub Actions.

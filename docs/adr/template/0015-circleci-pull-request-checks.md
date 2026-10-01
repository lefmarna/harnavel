# Pull request E2E and mutation testing on CircleCI

GitHub Free's 2,000 private-repository minutes are shared by every repository of the account, and
pull request E2E dominated them. Opted-in repositories run pull request E2E and mutation testing on
CircleCI's free plan; lint, Pest, the build, and deployments stay on GitHub Actions. CircleCI was
chosen over Cloud Build because its free plan stops instead of billing when credits run out, its
triggers skip draft pull requests, and service containers and caches keep job setup short.

GitHub Free cannot require checks on private repositories, so `delivery` remains the only gate. It
accepts CircleCI's E2E only through a record keyed by the deployed tree. CircleCI's API needs a
long-lived token with full account access, so records live in a Cloud Storage bucket that both
services reach with OIDC through Workload Identity Federation instead. Anything unproven falls back
to running E2E on GitHub Actions rather than failing, so CircleCI running out of credits never blocks
a deployment.

The tradeoffs: E2E and the deployed assets come from separate builds of the same tree; one more
service and a Google Cloud project with billing linked must be maintained; and when the base moves
after a pull request's last check, `delivery` still reruns everything on GitHub Actions.

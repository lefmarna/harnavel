# CircleCI

Pull request E2E and mutation testing can run on CircleCI, leaving GitHub Actions minutes for lint,
Pest, the build, and delivery. A repository opts in by setting the `E2E_RECORD_BUCKET` Actions
variable. Without it, every check stays on GitHub Actions as described in
[Branches and CI](branches-and-ci.md). [TADR-0015](adr/template/0015-circleci-pull-request-checks.md)
records why.

Both services run on free plans and stop rather than bill when their quota runs out. The only Google
Cloud resource is a bucket of tree records within the Cloud Storage Always Free limits, which apply
only in `us-central1`, `us-east1`, and `us-west1`.

## Responsibilities

| Check             | Pull request                          | Push to `develop` or `main`                     |
| ----------------- | ------------------------------------- | ----------------------------------------------- |
| Lint, Pest, build | Actions `quality`                     | Actions `delivery`, unless the tree is verified |
| E2E               | CircleCI `e2e`                        | Actions `delivery`, unless the tree is verified |
| Mutation testing  | CircleCI `mutation`, into `main` only | None                                            |

Manual `quality` and `mutation` dispatches still run everything on GitHub Actions.

## What CircleCI tests

The triggers start pipelines only for ready pull requests.
[`prepare-merge.sh`](../scripts/ci/prepare-merge.sh) merges the head into the current base, like the
merge commit that `pull_request` workflows check out. Jobs take environment variables from the
matching steps of `reusable-quality.yml` and `mutation.yml`, so app-specific test values live only
there, and mutation testing honors the `paths-ignore` of `mutation.yml`.

## How delivery trusts CircleCI

With CircleCI enabled, `quality` skips E2E and uploads its build as `build-<tree>-needs-e2e`. When
E2E passes, CircleCI checks that the tracked files still match the merge it tested and creates
`gs://<bucket>/<owner>/<repo>/e2e/<tree>.json`, a record of about 150 bytes. It authenticates with
its OIDC token through Workload Identity Federation and can read and create records but not
overwrite them, only under its own repository's prefix. A job whose merge result already has a record skips E2E.

`delivery` reuses such a build only if the usual provenance checks accept its run and it can read a
record for the same tree. Otherwise, including when CircleCI is out of credits or still running, it
runs lint, Pest, and E2E itself before deploying, so merge after the CircleCI check passes.

Pull request jobs can create records. As with build artifacts, this guards against mistakes, not
collaborators. Permissions are granted per repository and limited to its prefix: a pull request
can rewrite the record path in `.circleci/config.yml`, so a bucket-wide grant would let a
collaborator on one repository forge records for another. Records expire after 3 days, like build artifacts.

## One-time setup

### CircleCI

1. Create an organization integrated through the GitHub App (slug `circleci/<id>`); only its
   pipelines can skip draft pull requests.
2. In **Organization Settings** > **Contexts**, create a context named `e2e-record` with
   `E2E_RECORD_BUCKET` and `GCP_WORKLOAD_IDENTITY_AUDIENCE` from the Google Cloud setup below.

### Google Cloud

One project serves every repository. Cloud Storage needs a linked billing account even within the
Always Free limits.

```bash
PROJECT=<project-id>
OWNER=<github-owner>
CIRCLECI_ORG=<circleci-organization-id>
BUCKET=$PROJECT-e2e-records

gcloud config set project "$PROJECT"
gcloud services enable storage.googleapis.com sts.googleapis.com iam.googleapis.com
NUMBER=$(gcloud projects describe "$PROJECT" --format='value(projectNumber)')
POOLS=projects/$NUMBER/locations/global/workloadIdentityPools

gcloud storage buckets create "gs://$BUCKET" --location=us-central1 \
  --uniform-bucket-level-access --public-access-prevention
echo '{"rule":[{"action":{"type":"Delete"},"condition":{"age":3}}]}' > /tmp/lifecycle.json
gcloud storage buckets update "gs://$BUCKET" --lifecycle-file=/tmp/lifecycle.json

# CircleCI jobs of the owner's repositories create records; GitHub Actions reads them.
# Each repository gets its permissions when it is connected, below.
# The subject is the project ID because a V2 token's sub exceeds the 127-character limit.
gcloud iam workload-identity-pools create circleci --location=global
gcloud iam workload-identity-pools providers create-oidc circleci --location=global \
  --workload-identity-pool=circleci --issuer-uri="https://oidc.circleci.com/org/$CIRCLECI_ORG" \
  --allowed-audiences="$CIRCLECI_ORG" \
  --attribute-mapping="google.subject=assertion['oidc.circleci.com/project-id'],attribute.vcs_origin=assertion['oidc.circleci.com/vcs-origin']" \
  --attribute-condition="assertion['oidc.circleci.com/vcs-origin'].startsWith('github.com/$OWNER/')"

gcloud iam workload-identity-pools create github --location=global
gcloud iam workload-identity-pools providers create-oidc github --location=global \
  --workload-identity-pool=github --issuer-uri=https://token.actions.githubusercontent.com \
  --attribute-mapping='google.subject=assertion.sub,attribute.repository_owner=assertion.repository_owner,attribute.repository=assertion.repository' \
  --attribute-condition="assertion.repository_owner == '$OWNER'"

echo "E2E_RECORD_BUCKET=$BUCKET"
echo "GCP_WORKLOAD_IDENTITY_AUDIENCE=//iam.googleapis.com/$POOLS/circleci/providers/circleci"
echo "GCP_WORKLOAD_IDENTITY_PROVIDER=$POOLS/github/providers/github"
```

## Connecting a repository

1. Merge this setup into the repository.
2. Let the repository's CircleCI jobs create and read, and its GitHub Actions read, only records
   under its own prefix, with the variables from the Google Cloud setup:

   ```bash
   REPO=<repository-name>
   ONLY_OWN="expression=resource.name.startsWith('projects/_/buckets/$BUCKET/objects/$OWNER/$REPO/'),title=e2e-records-$REPO"
   CIRCLECI="principalSet://iam.googleapis.com/$POOLS/circleci/attribute.vcs_origin/github.com/$OWNER/$REPO"
   GITHUB="principalSet://iam.googleapis.com/$POOLS/github/attribute.repository/$OWNER/$REPO"
   for role in roles/storage.objectCreator roles/storage.objectViewer; do
     gcloud storage buckets add-iam-policy-binding "gs://$BUCKET" --role="$role" \
       --member="$CIRCLECI" --condition="$ONLY_OWN"
   done
   gcloud storage buckets add-iam-policy-binding "gs://$BUCKET" --role=roles/storage.objectViewer \
     --member="$GITHUB" --condition="$ONLY_OWN"
   ```

3. In CircleCI, create a project for the repository with the `.circleci/config.yml` pipeline and
   three triggers: **Non-draft PR opened**, **Pushes to open non-draft PRs**, and **PR marked ready for
   review**. Remove the default **All pushes** trigger.
4. In **Project Settings** > **Advanced**, enable **Auto-cancel redundant workflows**.
5. Set the Actions variables last, because they switch pull request E2E and mutation testing to
   CircleCI:

   ```bash
   gh variable set E2E_RECORD_BUCKET --body <bucket>
   gh variable set GCP_WORKLOAD_IDENTITY_PROVIDER --body <provider>
   ```

A CircleCI Free organization holds up to 10 projects. Branches created before the merge lack `.circleci/`, so their pipelines fail until they merge the base.

To return to GitHub Actions only, delete the `E2E_RECORD_BUCKET` variable, then the CircleCI project
and the repository's three bindings (`remove-iam-policy-binding` with the same `--condition`).

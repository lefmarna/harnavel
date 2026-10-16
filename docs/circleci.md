# CircleCI

A repository opts in to running pull request E2E and mutation testing on CircleCI
([TADR-0015](adr/template/0015-circleci-pull-request-checks.md)) by setting the `E2E_RECORD_BUCKET`
Actions variable. The Cloud Storage Always Free limits apply only in `us-central1`, `us-east1`, and
`us-west1`.

When no readable E2E record exists for the tree, including while CircleCI is out of credits or still
running, `delivery` runs lint, Pest, and E2E itself, so merge after the CircleCI check passes.

Grants are per repository and limited to its prefix: a pull request can rewrite the record path in
`.circleci/config.yml`, so a bucket-wide grant would let a collaborator on one repository forge
records for another. Like build artifacts, records guard against mistakes, not collaborators.

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

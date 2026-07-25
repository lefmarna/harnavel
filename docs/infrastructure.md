# Infrastructure Reference

Use this file for concrete runtime facts: ports, local services, and deployment constraints.
For exact commands, use `docs/commands.md`. Configurable ports and names are declared in
`.env.example` (the template defaults); override them locally in `.env`.

## Local Runtime Topology

| Service | Role | Default Port |
| --- | --- | --- |
| `nginx` | Web server (reverse proxy to PHP) | `8080` |
| `vite` | Frontend dev server | `5173` |
| `app` | PHP-FPM with Laravel | - |
| `mariadb` | Main database | `3306` |
| `mariadb-testing` | Testing database (isolated) | - |
| `mailpit` | Mail catcher (SMTP + Web UI) | `1025`, `8025` |

## Default Local Endpoints

- App: `http://localhost:8080`
- Vite: `http://localhost:5173`
- Mailpit: `http://localhost:8025`

## Deployment Profile

- Target hosting is XServer shared hosting.
- The app is served with `.htaccess` front-controller rewrites.
- Shared-hosting constraints drive two repo-wide rules documented in `AGENTS.md`: no long-running Node.js / queue workers, and no SSR.
- Build frontend assets before deployment. `public/build` must exist in deployment artifacts.
- Production observability is template-provided but disabled until
  `ERROR_NOTIFIER_DISCORD_WEBHOOK_URL` is configured. Notifications are sent in
  `production` and `develop` environments. See
  [`TADR-0004`](adr/template/0004-production-observability.md).

## XServer Server Toolchain

The remote steps in `.github/workflows/delivery.yml` run on the XServer host with
server-side binaries that are provisioned manually, outside this repository:

- PHP CLI: `/usr/bin/php8.5` — provided by XServer. Its `major.minor` must match the
  Docker PHP image tag in `.docker/php/Dockerfile` (the production-compatibility pin).
  Changing the PHP series requires confirming XServer support first.
- Composer: `~/opt/bin/composer-<version>` — a Composer PHAR uploaded manually to every
  deploy target (Develop and Production), named with its exact version.

Updating Composer is a three-surface change. Do it in this order:

1. Upload the new `composer-<version>` PHAR to `~/opt/bin/` on every XServer environment.
2. Update the Composer image tag in `.docker/php/Dockerfile` and both versioned paths in
   `.github/workflows/delivery.yml` (preflight and post-deploy) in the same commit.
3. Deploy.

The delivery workflow executes PHP and the versioned Composer PHAR over SSH before entering
maintenance mode or running `rsync --delete`. A missing, unreadable, corrupt, or mismatched PHAR
therefore fails without changing the deployed application. This check does not provision the PHAR,
so step 1 remains a manual precondition. `pnpm test:toolchain-versions` (run in CI lint) separately
keeps the Dockerfile and every `delivery.yml` toolchain reference from drifting apart.

### Production `vendor` handling

`vendor` is excluded from the deploy `rsync --delete`, so the previous release's packages
stay in place while files transfer. The post-deploy `composer install --no-dev` then
reconciles Composer-managed packages against `composer.lock`: packages removed from the lock are
uninstalled and changed packages are reinstalled. This does not promise to remove arbitrary files
that Composer does not manage. Deleting `vendor` during rsync instead would make the application
unbootable until `composer install` finishes.

## Deployment Availability and Recovery

Delivery is an in-place update, so each environment's workflows are serialized and an active run
is not cancelled by a newer push. After the remote toolchain preflight succeeds, deployment runs:

1. Stream `.github/deploy/enable-maintenance.php` to the configured PHP binary in the deploy
   directory. The workflow creates the directory when absent; the script uses only PHP core
   functions and does not load the existing `artisan` or `vendor` tree.
2. Wait for in-flight synchronous requests admitted before maintenance to finish. The inline
   duration in `delivery.yml` is app calibration and must exceed that app's longest synchronous
   request; the template default is 120 seconds.
3. `rsync --delete`, preserving `vendor`, `storage/framework/down`, and
   `storage/framework/maintenance.php`.
4. Composer install, cache rebuilds, and forced migrations.
5. `artisan up` only after every preceding command succeeds, followed by driver-independent
   removal and absence checks for the standalone maintenance files.
6. Verify three separate properties over HTTP. Under `enforce` all three must hold before the
   deployment is called successful; under `observe` only the first two do (see the staging note
   below):

   | Guarantee | Probe | What it proves |
   | --- | --- | --- |
   | Reachability | `HEALTHCHECK_URL` returns `200` | rewrite, PHP-FPM, and bootstrap serve requests |
   | Asset presence | the deployed manifest's asset returns `200` | `public/build` is present and servable |
   | Release identity | `<origin>/up/release` returns this deploy attempt's ID | a PHP response came from a front controller compiled from this release's bytes |

   The first two cannot distinguish releases: the health endpoint is a generic `200`, and the
   asset is served without executing PHP and keeps the previous release's file name whenever the
   frontend is unchanged. Release identity closes that gap. `Stamp release identity` rewrites the
   `LARAVEL_DEPLOYMENT_RELEASE_ID` literal in the deployment package's `public/index.php` — the
   file every request executes — to `<commit sha>-<run id>-<run attempt>`, so redeploying the same
   commit is still a distinct expectation. The identity is never read from a file at request time:
   stale bytecode could read a fresh file and report a release it is not running.

   The same response also reports the web runtime's OPcache revalidation directives, which are
   evaluated as a pair with the identity from that response. On XServer PHP 8.5, FastCGI and
   OPcache are always on, so this is the normal path rather than an edge case, and the workflow
   requires `opcache.validate_timestamps` enabled, no application `opcache.preload`, and an
   interpretable `opcache.revalidate_freq` no larger than 60 seconds (the response reports whether
   preloading is configured, never the configured path) — then waits out that
   interval, measured from the end of the remote writes, before accepting a matching identity.
   A response is authoritative about this release's runtime only when it carries this deploy
   attempt's identity and an OPcache payload whose structure matches the current schema (every
   required key present). The identity is a compile-time constant, so stale bytecode can return the
   new identity while still emitting the previous release's payload shape; the gate keys authority
   on the payload's own structure, which one method in one file produces, rather than a separate
   version number another file could revalidate out of step. A response with an older identity, or
   whose payload is missing the current keys, is retried rather than judged, so changing the
   response format cannot fail a deployment before the new release answers. Values that are uninterpretable, that differ between such responses, or that never
   revalidate fail closed: PHP-FPM cannot be restarted on shared hosting and a CLI `opcache_reset()` does not
   reach the web runtime, so waiting cannot repair them.

   The stage is the `RELEASE_IDENTITY_ENFORCEMENT` **configuration variable on each GitHub
   Environment** (Develop and Production), not a value in `delivery.yml`. To enforce, its value
   must name the repository, its numeric id, and the environment it applies to —
   `enforce:<owner>/<repo>@<repository id>:Production`.

   Assuming no variable of this name exists at a broader scope (see the scope caveat below, which
   overrides this whole paragraph), the other outcomes are:

   - unset, `observe`, or a well-formed value naming another repository or environment — the
     deployment observes, and a mistargeted value is reported as a warning;
   - any other value — the deployment fails in `Validate deployment configuration`, before the
     workflow touches the server, and the error prints the exact string this deployment expects.

   The target is part of the value because GitHub merges organization, repository, and environment
   variables into one `vars` context and does not expose which scope a value came from. A workflow
   therefore cannot read "only the environment-scoped variable". The numeric id is included
   alongside the slug because renaming a repository frees its old slug for reuse, so a slug alone
   could later match a different application; the slug stays in the value so a human can tell at a
   glance what a variable targets. Both `github.repository` and `github.repository_id` resolve in
   the expression itself, so this costs no API call, no extra token permission, and adds no new
   failure mode in a fail-closed path.

   Every run prints the effective stage and writes it to the job summary, and a value that was
   folded to observing because it names another target is reported as a warning. Read those before
   concluding that a switch took effect.

   What this does and does not guarantee:

   - **Prevented mechanically** — a variable at repository or organization scope reaching a
     *different* repository. It would have to name that repository and its id, and ids are never
     reused.
   - **Your responsibility** — re-measuring after anything that changes the runtime behind an
     environment: recreating the environment, repointing it at another host or directory, or
     changing that host's PHP / OPcache configuration. Clear the environment's variable, deploy to
     observe, and re-read the values before setting it again — after confirming the scope caveat
     below, which can keep an environment enforcing even with its own variable cleared. The gate
     cannot detect any of these.
   - **Accepted risk** — defining a variable of this name at repository or organization scope
     *within this repository*. GitHub documents that environment-level variables "won't overwrite
     variables in the `env` and `vars` contexts", and this workflow reads `vars` into a job-level
     `env`. A same-named variable at a broader scope therefore **shadows** the environment one, and
     which way that fails depends entirely on the broader value:

     | Broader-scope value | Result, whatever the environment says |
     | --- | --- |
     | absent | the environment's own value decides |
     | `observe`, or naming another repository or environment | observing |
     | exactly this repository, id, and environment | **enforcing** |

     The last row is the dangerous one: clearing or unsetting the environment variable does **not**
     return that environment to observing, so an environment can enforce without ever being
     measured and a re-measurement cycle silently cannot start. Define this variable only on
     environments, and delete any broader-scope one before relying on any procedure in this
     section.

   - `observe` — the release identity and the runtime directives are probed and logged as
     warnings, but the deployment still succeeds on reachability and asset presence alone, judged
     exactly as before this gate existed: any single attempt where both returned `200` is enough,
     so continuing to probe for identity never turns a healthy release into a withdrawal.
     Nothing new can withdraw a release. This matters because the endpoint itself may be
     invisible behind the previous release's bytecode, which is precisely what happens when
     `opcache.validate_timestamps` is off; failing there would take the site down before the
     configuration was ever measured. When identity cannot be confirmed, observing keeps polling up
     to the whole retry window, lengthening workflow completion — not downtime, since maintenance is
     already lifted; a release that confirms identity quickly still exits early. While observing,
     release identity is **not** yet verified.
   - `enforce` — identity and the OPcache precondition become success conditions and fail closed.

   Roll it out per environment, and note that this is not a one-time migration: a newly derived
   application starts unmeasured, and so does any environment whose runtime changed under it.

   Every step below assumes **no variable of this name exists at repository or organization
   scope**. Confirm that first: while one does, it decides the stage for every environment it
   names, so neither "deploy with the variable unset" nor "clear the variable" does what it says.
   Read the first run's summary line rather than assuming an environment observes.

   1. Deploy with the variable unset, so the environment observes.
   2. Read the recorded values from that environment's workflow log. One environment's directives
      never imply the other's, and the CLI's never imply the web SAPI's, so Develop and Production
      are measured separately.
   3. Confirm the environment served this deploy attempt's identity, that the OPcache payload
      carried the current keys, and that `validateTimestamps` is true, `preloadConfigured` is
      false, and `revalidateFrequency` is at most 60.
   4. Set that environment's `RELEASE_IDENTITY_ENFORCEMENT` variable to
      `enforce:<owner>/<repo>@<repository id>:<environment>` — this repository, its numeric id, and
      this environment, such as `enforce:lefmarna/laravel-app-template@123456789:Production`
      (Settings → Environments → the environment → Variables) — and deploy again, checking the job
      summary line to confirm the run actually enforced. A rejected or mistargeted value is
      reported by the run itself, and the error message prints the exact string this deployment
      expects.

   Setting the variable takes effect on the next run without a commit, and — with no broader-scope
   variable present — clearing it returns the environment to observing. If a run's stage does not
   match what the environment says, in either direction, look for a variable of the same name at
   repository or organization scope and delete it: per GitHub's documented exception it shadows the
   environment value in the `vars` context. A mistargeted broader value is reported by the run's
   warning; one that happens to match this deployment is not distinguishable from a correct
   environment setting, which is why the check is a precondition rather than a diagnosis.

   The URL comes from the per-environment `HEALTHCHECK_URL` GitHub secret (normally
   `https://<domain>/up`) and is URL-parser-validated before any remote change, so a missing
   value — or one the health request could not pass — fails the run without touching the server.
   It must be the **canonical HTTPS URL**, without whitespace, userinfo, query, or fragment:
   the asset origin is derived from it, and the probes do not follow redirects, so any URL that
   redirects fails verification and withdraws an otherwise healthy release. `.htaccess` and
   `.htaccess.develop` redirect all plain HTTP to HTTPS, which is why `http://` is rejected up
   front; a host that redirects (for example `www` when `www` redirects to the apex) cannot be
   detected statically and is reported as a redirect hint when verification fails. The deploy
   attempt identity is validated in the same pre-remote step, because an empty expectation would
   match an empty response. The check retries across the server's file-cache revalidation window,
   so a release that has not propagated yet is retried rather than failed. When verification ends
   without success — persistent failure, an identity that never matched under `enforce`, an
   unusable OPcache configuration under `enforce`, or manual cancellation —
   a separate workflow step re-runs the maintenance installer to withdraw the release behind the
   planned 503. That step is gated on a recorded lift attempt rather than on the lift step's own
   outcome, so a lift whose SSH connection dropped after the remote commands completed is still
   withdrawn; failures before the lift attempt leave maintenance (or the intact old release)
   untouched.

The installer writes Laravel-compatible maintenance state and a standalone response through
temporary files and same-directory renames. The response is loaded by `public/index.php` before
Composer dependencies and request middleware, and always terminates with 503: clients that prefer
JSON receive a static JSON body, while clients that accept HTML receive static HTML. The latter
includes Inertia XHR visits, which send `X-Requested-With` together with an HTML `Accept` header.
It intentionally has no inquiry ID; runtime-generated 500/503 responses still carry their
per-request inquiry ID as specified by
[`TADR-0004`](adr/template/0004-production-observability.md). See
[`TADR-0006`](adr/template/0006-planned-maintenance-deployments.md) for the availability decision.

Deployment fails closed: an rsync, Composer, cache, migration, cancellation, final SSH, or
release health verification failure may leave the application in maintenance mode. Prefer
rerunning the corrected workflow, which
can recreate the maintenance files without booting an empty or partially transferred tree, then
completes the normal `up` step. If manual recovery is necessary, first confirm the code,
Composer-managed dependencies, caches, and migrations are coherent, then run the following from
the deploy directory:

```bash
/usr/bin/php8.5 artisan up &&
    rm -f storage/framework/down storage/framework/maintenance.php &&
    test ! -e storage/framework/down &&
    test ! -e storage/framework/maintenance.php
```

Never bring the application up merely to clear a failed workflow while the release state is
uncertain.

When the workflow reports that the release did not serve its own identity, the deployed files
reached the server but the web runtime is still executing older bytecode, so rerunning the
workflow only helps once the runtime revalidates. When it reports that identity cannot be
guaranteed, the runtime configuration itself is the blocker: fix `opcache.validate_timestamps`,
`opcache.preload`, or `opcache.revalidate_freq` for the web SAPI (the XServer PHP settings for
that directory, not the CLI), then rerun. `<origin>/up/release` reports the live values whenever
the application is up.

## Troubleshooting

- Port conflicts: update `NGINX_PORT`, `VITE_PORT`, and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

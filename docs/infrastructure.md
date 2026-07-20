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
6. Verify the released application returns `200` from the public health endpoint and that a
   built asset referenced by the deployed Vite manifest is served (the health endpoint does not
   load built assets, so a missing or unservable `public/build` is only caught by the asset
   request). The URL comes from the per-environment `HEALTHCHECK_URL` GitHub secret (normally
   `https://<domain>/up`) and is URL-parser-validated before any remote change, so a missing
   value — or one the health request could not pass — fails the run without touching the server.
   It must be the **canonical HTTPS URL**, without whitespace, userinfo, query, or fragment:
   the asset origin is derived from it, and the probes do not follow redirects, so any URL that
   redirects fails verification and withdraws an otherwise healthy release. `.htaccess` and
   `.htaccess.develop` redirect all plain HTTP to HTTPS, which is why `http://` is rejected up
   front; a host that redirects (for example `www` when `www` redirects to the apex) cannot be
   detected statically and is reported as a redirect hint when verification fails. The check retries across the server's file-cache revalidation
   window. When verification ends without success — persistent failure or manual cancellation —
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

## Troubleshooting

- Port conflicts: update `NGINX_PORT`, `VITE_PORT`, and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

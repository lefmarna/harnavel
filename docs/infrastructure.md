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
2. `rsync --delete`, preserving `vendor`, `storage/framework/down`, and
   `storage/framework/maintenance.php`.
3. Composer install, cache rebuilds, and forced migrations.
4. `artisan up` only after every preceding command succeeds.

The installer writes Laravel-compatible maintenance state and a standalone response through
temporary files and same-directory renames. The response is loaded by `public/index.php` before
Composer dependencies and request middleware, and always terminates with 503: JSON requests receive
a static JSON body, while `/up` and other requests receive static HTML. It intentionally has no
inquiry ID; runtime-generated 500/503 responses still carry their per-request inquiry ID as
specified by [`TADR-0004`](adr/template/0004-production-observability.md). See
[`TADR-0006`](adr/template/0006-planned-maintenance-deployments.md) for the availability decision.

Deployment fails closed: an rsync, Composer, cache, migration, cancellation, or final SSH failure
may leave the application in maintenance mode. Prefer rerunning the corrected workflow, which
can recreate the maintenance files without booting an empty or partially transferred tree, then
completes the normal `up` step. If manual recovery is necessary, first confirm the code,
Composer-managed dependencies, caches, and migrations are coherent, then run
`/usr/bin/php8.5 artisan up` from the deploy directory. Never bring the application up merely to
clear a failed workflow while the release state is uncertain.

## Troubleshooting

- Port conflicts: update `NGINX_PORT`, `VITE_PORT`, and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

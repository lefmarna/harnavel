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

The post-deploy step in `.github/workflows/delivery.yml` runs on the XServer host with
server-side binaries that are provisioned manually, outside this repository:

- PHP CLI: `/usr/bin/php8.5` — provided by XServer. Its `major.minor` must match the
  Docker PHP image tag in `.docker/php/Dockerfile` (the production-compatibility pin).
  Changing the PHP series requires confirming XServer support first.
- Composer: `~/opt/bin/composer-<version>` — a Composer PHAR uploaded manually to every
  deploy target (Develop and Production), named with its exact version.

Updating Composer is a three-surface change. Do it in this order:

1. Upload the new `composer-<version>` PHAR to `~/opt/bin/` on every XServer environment.
2. Update the Composer image tag in `.docker/php/Dockerfile` and the versioned path in
   `.github/workflows/delivery.yml` in the same commit.
3. Deploy.

Merging the workflow change before step 1 breaks the next deploy at the post-deploy
`composer install`. `pnpm test:toolchain-versions` (run in CI lint) keeps the Dockerfile
and `delivery.yml` versions from drifting apart; it cannot see the server, so step 1 stays
a manual precondition.

### Production `vendor` handling

`vendor` is excluded from the deploy `rsync --delete`, so the previous release's packages
stay in place while files transfer. The post-deploy `composer install --no-dev` then
reconciles `vendor/` against `composer.lock`: packages removed from the lock are
uninstalled and changed packages are reinstalled wholesale, so stale files do not
accumulate. Deleting `vendor` during rsync instead would take the site fully down until
`composer install` finishes.

## Troubleshooting

- Port conflicts: update `NGINX_PORT`, `VITE_PORT`, and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

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
6. Verify two separate properties over HTTP. Both must hold before the deployment is called successful:

   | Guarantee | Probe | What it proves |
   | --- | --- | --- |
   | Reachability | `HEALTHCHECK_URL` returns `200` | rewrite, PHP-FPM, and bootstrap serve requests |
   | Asset presence | the deployed manifest's asset returns `200` | `public/build` is present and servable |

   Both probes and the retry loop live in `scripts/verify-release-health.mjs`, which
   `delivery.yml` invokes in one line. It deliberately has no dependencies: the deploy job runs
   neither `pnpm install` nor `setup-node`, so anything beyond the runner's bundled Node would
   fail only after maintenance had already been lifted. `pnpm test:release-health` exercises that
   script as a real process against a local HTTP server.

   An environment whose document root is protected by Basic authentication — `.htaccess.develop`
   does this in some applications — also needs the optional per-environment
   `HEALTHCHECK_BASIC_AUTH` secret, in `user:pass` form. When it is set, both probes send the
   credential; when it is unset, neither does, so leave it unset on environments without Basic
   authentication. Both matter: the health endpoint and the built asset sit behind the same
   protection, and a single unauthenticated probe returns `401`, which fails reachability and
   withdraws a healthy release. Do not put the credential in `HEALTHCHECK_URL` — userinfo is
   rejected there — and it is never written to the log; a run that keeps seeing `401` prints a
   hint to set this secret. The check retries across the server's file-cache revalidation window,
   so a release that has not propagated yet is retried rather than failed. When verification ends
   without success — persistent failure or manual cancellation —
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

## OPcache Revalidation Measurement

The deployment no longer gates on OPcache revalidation settings at runtime, but the
precondition — that the web SAPI revalidates timestamps fast enough for stale bytecode to
self-resolve before verification runs — still holds. Re-measure after changing the PHP version,
OPcache configuration, or hosting provider.

### Procedure

1. Generate a non-guessable probe filename:
   ```bash
   PROBE="opcache-probe-$(openssl rand -hex 8).php"
   ```

2. From the environment's `public/` directory over SSH (`ssh -p 10022 <user>@<host>`), create it,
   read it, and delete it in one block. Read `ini_get()` rather than
   `opcache_get_configuration()`: the latter can be sealed off by `opcache.restrict_api`.
   ```bash
   cat > "$PROBE" <<'PHP'
   <?php
   header('Content-Type: application/json');
   $loaded = extension_loaded('Zend OPcache');
   $preload = ini_get('opcache.preload');
   echo json_encode([
       'sapi' => php_sapi_name(),
       'phpVersion' => PHP_VERSION,
       'extensionLoaded' => $loaded,
       'enable' => $loaded ? ini_get('opcache.enable') : null,
       'validate_timestamps' => $loaded ? ini_get('opcache.validate_timestamps') : null,
       'revalidate_freq' => $loaded ? ini_get('opcache.revalidate_freq') : null,
       'preloadConfigured' => $loaded ? ($preload === false ? null : $preload !== '') : null,
   ], JSON_PRETTY_PRINT);
   PHP
   chmod 644 "$PROBE"
   # Basic 認証のある環境では -u '<user>' だけを渡す。curl がパスワードを対話的に尋ねる。
   # user:pass をそのまま書くと、共有ホストの shell history と ps の引数に平文で残る。
   curl -s "https://<canonical-host>/$PROBE"
   rm -f "$PROBE" && ls "$PROBE"
   ```

3. Confirm `sapi` is a web SAPI (`cgi-fcgi`, `fpm-fcgi`, `apache2handler`). A `cli` value means the
   request never reached the web runtime and the reading is worthless.

4. **Confirm the probe is gone** — the trailing `ls` must report "No such file". On an environment
   without Basic authentication the file is publicly readable while it exists, which is why the
   filename is randomised and the block deletes it in the same run.

### Acceptance criteria

| Directive | Required value | Rationale |
| --- | --- | --- |
| `validate_timestamps` | `1` (On) | Without timestamp validation, stale bytecode persists until the process exits |
| `revalidate_freq` | ≤ the `sleep` in `Wait for OPcache revalidation` (10 seconds) | The deployment waits exactly that long, while the planned 503 is still up |
| `preload` | empty / unset | Preloaded code is never re-checked against timestamps |

Both sides of the `revalidate_freq` row are seconds. Raising one means raising the other: the wait
must cover the interval, or the release is published before the runtime has re-read it.

Do not lean on the post-deploy sequence instead. It runs far longer than the window in practice —
`composer install`, three cache rebuilds, `migrate`, and an SSH round trip — but nothing bounds it
from below, so it cannot carry the guarantee.

An environment above the threshold fails silently: maintenance lifts while the previous release's
bytecode is still resident, both probes answer `200`, and the workflow reports success while old
code runs against the migrated schema.

### The platform assumption (measured July 2026)

**XServer's default configuration satisfies the criteria above.** Three environments — two
production domains and one develop subdomain, across two independent applications — all reported
`cgi-fcgi`, PHP 8.5.5, `validate_timestamps=1`, `revalidate_freq=2`, and no preload.

This is a property of the hosting platform, not of any one application, so it is **not** a
per-application prerequisite. A newly derived application deploys on this assumption without
measuring anything first.

Re-measure when the assumption itself could have moved:

- the environment leaves XServer, or moves to a different plan or server
- the domain's PHP version changes
- a `.user.ini` lands in the document root — `delivery.yml` excludes it from rsync, so it is
  server-side state the repository never sees and can override these directives
- the symptom appears: old behaviour after a green deployment

There is no automatic check, and adding one is not cheap: the directives differ between the CLI and
the web SAPI, so a per-run check would have to serve the web runtime's own configuration over HTTP
from the deployed application. [`TADR-0006`](adr/template/0006-planned-maintenance-deployments.md)
weighs that against a measured platform assumption and takes the assumption.

## Troubleshooting

- Port conflicts: update `NGINX_PORT`, `VITE_PORT`, and `MARIADB_PORT` in `.env`.
- Broken containers: `mise run destroy`, then `mise run build`, then `mise run up`.
- Reset database: `mise run fresh`.
- Inspect runtime: `mise run app` and `mise run logs`.

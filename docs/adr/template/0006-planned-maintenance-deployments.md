# Planned maintenance for in-place XServer deployments

## Status

Accepted.

## Context

XServer deployments update the live directory in place. Preserving `vendor` avoids a guaranteed
hard outage, but application files, Composer-managed dependencies, caches, and database schema are
still reconciled at different times. Serving requests during that interval can expose an
internally inconsistent release.

Laravel's front controller can load a standalone maintenance response before Composer dependencies.
However, `artisan down` requires the current release to bootstrap, and Laravel's generated
responder returns to the front controller for JSON requests and maintenance-excluded paths. Those
properties prevent an empty or partially transferred target from being repaired safely and can
expose the mixed release through JSON endpoints or `/up`.

A repository-controlled, bootstrap-independent responder trades a short, explicit 503 window for
deterministic behavior during the non-atomic update. A failed deployment can also leave maintenance
mode active, so activation, cancellation, and recovery semantics are part of the availability
decision rather than incidental workflow details.

## Decision

Use fail-closed planned maintenance for XServer delivery:

- Serialize each environment's delivery workflows and do not cancel an active run for a newer
  push.
- Execute the configured PHP binary and exact versioned Composer PHAR over SSH before changing the
  remote application.
- Create the deploy directory when absent, then stream `.github/deploy/enable-maintenance.php` to
  the configured PHP binary with that directory as its working directory. The installer must not
  load the target's `artisan`, Composer autoloader, or application bootstrap.
- Use PHP core functions to write Laravel-compatible `down` state and a standalone
  `maintenance.php` through temporary files and same-directory renames.
- Return 503 and terminate before autoload for every request. Return a static JSON body when JSON is
  requested and static HTML for `/up` and all other requests; do not inherit Laravel maintenance
  exclusions or bypass behavior for a planned deployment.
- Preserve `vendor`, `storage/framework/down`, and `storage/framework/maintenance.php` during
  rsync.
- Run Composer install, cache rebuilds, and migrations while the standalone response is active.
- Run `artisan up` only after every post-deploy command succeeds. On failure or cancellation, keep
  maintenance mode active until a corrected workflow completes or an operator verifies a coherent
  release and brings it up manually.

The planned response is returned by `public/index.php` before Composer and request middleware. It
therefore cannot have a request-specific ID or `X-Request-Id` header. Runtime-generated failures
continue to use the shared `503` Blade view with an inquiry ID. This is the planned-maintenance
exception to [`TADR-0004`](0004-production-observability.md), not a weakening of runtime error
correlation.

## Considered alternatives

- **Serve traffic throughout the in-place update.** Rejected because partial availability can
  expose combinations of code, dependencies, caches, and schema that were never tested together.
- **Always run `artisan up` in a cleanup step.** Rejected because it can publish a release after a
  failed Composer install or migration.
- **Cancel an active delivery when a newer commit arrives.** Rejected because cancellation after
  `down` can strand the application and skip the verified recovery sequence.
- **Run `artisan down --render` from the current release.** Rejected because an initial target has
  no `artisan`, and a retry after partial rsync cannot assume the current tree can bootstrap.
- **Use Laravel's generated pre-render responder unchanged.** Rejected because it deliberately
  returns to `public/index.php` for JSON requests and excluded paths such as `/up`, allowing those
  requests to load Composer during the mixed-release window.
- **Use atomic release directories and a symlink switch.** Deferred because it requires a larger
  XServer document-root, shared-storage, and rollback design than the current applications need.

## Consequences and accepted risks

- Successful deployments return a short planned 503 instead of serving an inconsistent release.
- A failed or manually cancelled deployment may remain fully unavailable until rerun or manual
  recovery; this is intentional fail-closed behavior and requires the documented runbook.
- An empty target and a target left partially transferred can both re-enter the normal rsync repair
  path without booting their current application.
- `/up` intentionally returns 503 during deployment; health monitoring therefore observes the
  planned outage instead of a misleading application-level success.
- The planned 503 has no inquiry ID because it bypasses application bootstrap by design.
- Delivery changes remain hybrid under [`TADR-0003`](0003-shared-surface-convergence.md): derived
  applications adopt the lifecycle while preserving their app-specific rsync exclusions.

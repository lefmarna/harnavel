# Planned maintenance for in-place XServer deployments

## Status

Accepted.

## Context

XServer deployments update the live directory in place. Preserving `vendor` avoids a guaranteed
hard outage, but application files, Composer-managed dependencies, caches, and database schema are
still reconciled at different times. Serving requests during that interval can expose an
internally inconsistent release.

Laravel's front controller can load a standalone maintenance response before Composer
dependencies, but `artisan down` requires the current release to bootstrap, and Laravel's generated
responder returns to the front controller for JSON requests and maintenance-excluded paths. Those
properties prevent an empty or partially transferred target from being repaired safely and can
expose the mixed release through JSON endpoints or `/up`.

Shared hosting cannot restart PHP-FPM, and a CLI `opcache_reset()` does not reach the web runtime.
Anything the deployment cannot prove over HTTP after the fact therefore cannot be repaired by
waiting.

## Decision

Use fail-closed planned maintenance for XServer delivery.

**Serialization and activation.** Serialize each environment's delivery workflows and do not cancel
an active run for a newer push. Execute the configured PHP binary and exact versioned Composer PHAR
over SSH before changing the remote application. Stream a repository-controlled installer to the
configured PHP binary; it must not load the target's `artisan`, Composer autoloader, or application
bootstrap, and it writes Laravel-compatible `down` state plus a standalone `maintenance.php` using
only PHP core functions through temporary files and same-directory renames.

**Response during maintenance.** Return 503 and terminate before autoload for every request: static
JSON when the client prefers JSON, static HTML whenever the client accepts HTML, including Inertia
XHR visits that combine an HTML `Accept` header with `X-Requested-With`. Do not inherit Laravel's
maintenance exclusions or bypass behavior for a planned deployment. The responder runs from
`public/index.php`, so requests the web server resolves directly from an existing file or directory
never reach it — see the accepted risk below.

**Ordering.** After the standalone response is active and before destructive rsync, wait a bounded,
app-calibrated drain interval exceeding the longest synchronous request; shared hosting cannot count
active requests, so this is a time-based approximation. Preserve `vendor`,
`storage/framework/down`, and `storage/framework/maintenance.php` during rsync. Run Composer
install, cache rebuilds, and migrations while the standalone response is active. Run `artisan up`
only after every post-deploy command succeeds, then remove the standalone files independently of the
configured maintenance driver and verify their removal. On failure or cancellation, keep maintenance
active until a corrected workflow completes or an operator verifies a coherent release manually.

**Verification.** After maintenance is lifted, require two distinct HTTP observations before calling
the deployment successful: **reachability** (the public health endpoint returns 200) and **asset
presence** (a built asset referenced by the deployed Vite manifest is served). `artisan up` proves
CLI bootstrap only; the health endpoint carries no identity and does not load built assets.

Verification introduces no authentication of its own, but an environment whose document root is
already protected — Basic authentication on a develop host, for example — must have that existing
credential presented on **both probes**: they share one protection, and a single
unauthenticated probe returns 401, failing reachability and withdrawing a healthy release even
while observing. The credential is an optional per-environment secret, never embedded in the health
URL and never logged. It belongs to the environment, not to the template: it is not copied between
applications, and environments without such protection leave it unset.

**OPcache revalidation (measured, not gated).** XServer's web SAPI (cgi-fcgi, PHP 8.5) was measured
in July 2026 across three environments — two production domains and one develop subdomain, spanning
two independent applications. Every environment reported `opcache.validate_timestamps = 1` and
`opcache.revalidate_freq = 2` with no application preload.
Verification therefore waits a fixed settle interval before its first probe, rather than reading the
directives back per run or gating on them. The wait is what makes the guarantee checkable: an
environment whose revalidation interval is at or below the settle interval has necessarily
revalidated by the time any `200` is observed, so a release identity gate would be checking a
question the clock has already answered. The measured 2 seconds sits well under it.

The condition is exactly that — `validate_timestamps` on, no preload, and a revalidation interval at
or below the settle interval — and nothing weaker. In particular, "the post-deployment sequence runs
longer than the revalidation window" is not a substitute, however reliably true it looks: nothing
bounds that sequence from below, so it cannot carry a fail-closed guarantee. The measurement
procedure — a one-file PHP probe that reads `ini_get()` values, deployed to a non-guessable filename
and deleted immediately — is documented in `docs/infrastructure.md` for re-measurement after hosting
changes.

This holds only while the measurement holds. Re-measure after changing the PHP version, the OPcache
configuration, or the hosting provider; an environment that has not been measured is unknown, not
covered. An environment above the settle interval needs a longer wait or a different runtime, and a
runtime that never revalidates needs a different decision here, because waiting cannot repair it.

**Withdrawal.** Withdraw a release whose verification did not end in success — persistent failure or
manual cancellation — by re-running the standalone installer from a separate step gated on a
recorded maintenance-lift attempt. The lift step's own outcome cannot gate withdrawal: a connection
lost after the remote `artisan up` completes reports failure while the site is already live.
Withdrawal is idempotent when maintenance is still active; failures before the lift attempt keep
their existing fail-closed state and never take an intact old release down.

The planned response is returned before Composer and request middleware, so it cannot have a
request-specific ID or `X-Request-Id` header. Runtime-generated failures continue to use the shared
`503` Blade view with an inquiry ID. This is the planned-maintenance exception to
[`TADR-0004`](0004-production-observability.md), not a weakening of runtime error correlation.

Operational procedure, per-environment rollout, and recovery live in
[`docs/infrastructure.md`](../../infrastructure.md).

## Considered alternatives

- **Serve traffic throughout the in-place update.** Rejected: partial availability can expose
  combinations of code, dependencies, caches, and schema never tested together.
- **Always run `artisan up` in a cleanup step.** Rejected: it can publish a release after a failed
  Composer install or migration.
- **Cancel an active delivery when a newer commit arrives.** Rejected: cancellation after `down` can
  strand the application and skip the verified recovery sequence.
- **Run `artisan down --render` from the current release.** Rejected: an initial target has no
  `artisan`, and a retry after partial rsync cannot assume the current tree can bootstrap.
- **Use Laravel's generated pre-render responder unchanged.** Rejected: it deliberately returns to
  the front controller for JSON requests and excluded paths such as `/up`, letting those requests
  load Composer during the mixed-release window.
- **Start rsync immediately after enabling maintenance.** Rejected: a request admitted before
  activation can still execute while files and schema change.
- **Rely on `artisan up` alone for recovery.** Rejected: the installer writes file-based state
  independently of the configured maintenance driver; another driver can report the application is
  already up while the standalone responder remains.
- **End the delivery at `artisan up`.** Rejected: CLI bootstrap does not prove the web entry path
  serves requests.
- **Gate the deployment on release identity and OPcache directives.** Rejected on measurement: stamp
  a deploy-attempt identity into the front controller, serve it from a PHP endpoint, read the web
  SAPI's revalidation directives from the same response, and fail closed unless this attempt's
  identity answers under a safe configuration. It withstands a runtime that never revalidates — but
  the measurement above found none, and the cost is high: the endpoint publishes the deployed commit
  and the runtime's configuration, an unmeasured environment must first run in an observing stage or
  it withdraws its own first healthy release, and that stage is per-environment configuration the
  workflow cannot read back. Measuring a runtime once is cheaper than carrying a permanent gate
  against a condition it does not have.
- **Restart PHP-FPM or reset OPcache after deployment.** Rejected: shared hosting grants neither.
- **Leave an unhealthy release live and only fail the workflow.** Rejected: serving known-broken
  responses contradicts the fail-closed decision.
- **Use atomic release directories and a symlink switch.** Deferred: it requires a larger document
  root, shared-storage, and rollback design than the current applications need.

## Consequences and accepted risks

- Successful deployments return a bounded planned 503 window instead of serving an inconsistent
  release, and every deployment includes a fixed drain delay that cannot guarantee completion of a
  request outliving the configured interval.
- A failed or manually cancelled deployment may remain fully unavailable until rerun or manual
  recovery. `/up` intentionally returns 503 during deployment, so health monitoring observes the
  planned outage rather than a misleading success.
- Static assets the web server resolves directly — hashed build files, `manifest.webmanifest`,
  images — bypass the front-controller responder, because the maintenance response is loaded by
  `public/index.php` and the `.htaccess` front-controller rewrite only applies to paths that are not
  an existing file or directory. During the rsync `--delete` window such a request can transiently
  return 404 or serve a mismatched hashed chunk to an already-loaded SPA tab. This is an accepted
  risk because the exposure is bounded to asset skew, never a mixed combination of application
  code, dependencies, caches, or schema. It is not self-healing: a failed lazy chunk raises Vite's
  `vite:preloadError` and rethrows, and no handler here reloads on it, so that tab stays broken
  until the user reloads — after which it receives the planned 503. Serving every path through the
  responder would require a maintenance-gated web-server rewrite and is deferred with the
  atomic-release design; an automatic reload handler would be a separate frontend decision.
- Verification proves that *a* release answers and that the deployed manifest's asset is served, not
  that *this* deployment's bytecode produced the response. Nothing in the workflow observes release
  identity any more; the guarantee rests on the environment's revalidation interval being at or below
  the settle interval verification waits. If it is not, the deployment reports success while the
  previous release serves — and it does so silently, because the only signal is the symptom (old
  behaviour after a green deployment). The runbook in
  [`docs/infrastructure.md`](../../infrastructure.md) exists to establish and re-check that bound.
- Every deployment pays the settle interval, whether or not the environment needs it. That is the
  price of not measuring the directives per run.
- Verification requires a per-environment canonical HTTPS health URL, parser-validated before any
  remote change so a missing or unusable value cannot strand the site in maintenance. The probes do
  not follow redirects, because following them would let an absent build asset resolve to some other
  200 response and defeat the asset check. A verification false negative (for example runner egress
  failure) withdraws a healthy release; recovery is rerunning the workflow.
- Delivery changes remain hybrid under [`TADR-0003`](0003-shared-surface-convergence.md): derived
  applications adopt the lifecycle while preserving their app-specific rsync exclusions and drain
  duration.

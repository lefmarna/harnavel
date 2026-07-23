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
- Return 503 and terminate before autoload for every request. Return a static JSON body only when
  the client prefers JSON, and static HTML whenever the client accepts HTML, including Inertia XHR
  visits that combine an HTML `Accept` header with `X-Requested-With`. Do not inherit Laravel
  maintenance exclusions or bypass behavior for a planned deployment.
- After the standalone response is active and before destructive rsync, wait for an app-calibrated
  bounded drain interval that exceeds the longest synchronous request. Shared hosting cannot count
  active requests, so this is a time-based approximation.
- Preserve `vendor`, `storage/framework/down`, and `storage/framework/maintenance.php` during
  rsync.
- Run Composer install, cache rebuilds, and migrations while the standalone response is active.
- Run `artisan up` only after every post-deploy command succeeds, then remove the standalone
  `down` and `maintenance.php` files independently of the configured maintenance driver and verify
  their removal. On failure or cancellation, keep maintenance mode active until a corrected
  workflow completes or an operator verifies a coherent release and brings it up manually.
- Stamp a deploy-attempt identity into the deployment package before rsync, as a PHP literal in
  the front controller every request executes. The identity combines the commit SHA with the
  workflow run and attempt, so redeploying the same commit does not accept the previous attempt's
  response. Never resolve it from a file the application re-reads at request time: stale bytecode
  can read a fresh data file and report an identity it is not running.
- After maintenance is lifted, require three distinct HTTP observations before calling the
  deployment successful: **reachability** — the public health endpoint returns 200;
  **asset presence** — a built asset referenced by the deployed Vite manifest is served; and
  **release identity** — a PHP-served endpoint returns the stamped identity of this deploy
  attempt. `artisan up` proves CLI bootstrap only; the health endpoint carries no identity and
  does not load built assets; the asset is static and never executes PHP, and an unchanged
  frontend produces the previous release's file name. Only the third observation distinguishes
  "some release answered" from "this release answered". Under enforcement a mismatch never
  succeeds: it keeps retrying and then fails the verification. The staged rollout below defines
  what the observing stage does instead.
- Read the web runtime's OPcache revalidation directives from that same response, pair them with
  the identity from the same response, and treat them as a precondition rather than an
  observation. Only a response that carries this deploy attempt's identity is authoritative about
  this release's runtime; a response still carrying an older identity is treated as "not switched
  over yet" and retried, whatever its schema, so that changing the response format cannot make the
  first deployment fail before the new release ever answers. Timestamp revalidation must be enabled, application preloading must be unused, and
  the revalidation interval must be interpretable and within a bounded maximum; the workflow also
  waits out that interval, measured from the completion of the remote writes, before accepting a
  match. Uninterpretable values, values that differ between responses, and configurations that
  never revalidate all fail closed, because shared hosting cannot restart PHP-FPM and CLI OPcache
  operations do not reach the web runtime. Waiting longer cannot repair them.
- Adopt the identity gate in two stages per environment. In the observing stage the deployment
  still succeeds on reachability and asset presence alone, while the identity and the runtime
  directives are probed and recorded as warnings; only then is the gate switched to enforcing.
  Observing must not fail on identity: the new endpoint may be invisible behind the previous
  release's bytecode — which is exactly what happens on the runtimes worth measuring — so an
  identity-failing observation stage would withdraw healthy releases before anything is measured.
  The directives cannot be predicted from another environment or from the CLI.
- Withdraw a release whose verification did not end in success — persistent failure or manual
  cancellation — by re-running the standalone installer from a separate step gated on a recorded
  maintenance-lift attempt. The lift step's own outcome cannot gate withdrawal: a connection
  lost after the remote `artisan up` completes reports failure or cancellation while the site is
  already live. Withdrawal is idempotent when maintenance is still active; failures before the
  lift attempt keep their existing fail-closed state and never take an intact old release down.

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
- **Start rsync immediately after enabling maintenance.** Rejected because a request admitted
  before activation can still execute while files and schema change.
- **Rely on `artisan up` alone for recovery.** Rejected because the installer writes file-based
  state independently of the configured maintenance driver; another driver can report the
  application is already up while the standalone responder remains.
- **End the delivery at `artisan up`.** Rejected because CLI bootstrap does not prove the web
  entry path serves requests; a release that boots for artisan but fails over HTTP would stay
  live behind a green delivery.
- **Treat a health 200 plus an asset 200 as a successful deployment.** Rejected because neither
  response carries release identity: the health endpoint is a generic 200, and the asset is
  served without executing PHP and can be byte-identical to the previous release. A stale web
  OPcache entry could therefore end the workflow successfully while the old code is still serving
  against the new schema and caches, and the withdrawal step would never run.
- **Publish the release identity from a data file the request reads.** Rejected because the old
  bytecode can read the new file and report an identity it is not executing, converting a
  fail-closed check into a false positive.
- **Extend the retry window instead of checking revalidation settings.** Rejected because a
  runtime that never revalidates timestamps never converges, and XServer grants no PHP-FPM
  restart. The failure mode is not slowness but permanence.
- **Restart PHP-FPM or reset OPcache after deployment.** Rejected because shared hosting grants
  neither, and `opcache_reset()` from the CLI applies to the CLI process, not the web runtime.
- **Serve the identity endpoint behind a shared secret.** Rejected for now because it adds a
  per-environment secret to provision on both GitHub and the server before any deployment can
  succeed; the exposed value is a deploy-attempt identifier for a private repository.
- **Leave an unhealthy release live and only fail the workflow.** Rejected because serving
  known-broken responses contradicts the fail-closed decision; withdrawing behind the planned 503
  restores the deterministic state the runbook already covers.
- **Use atomic release directories and a symlink switch.** Deferred because it requires a larger
  XServer document-root, shared-storage, and rollback design than the current applications need.

## Consequences and accepted risks

- Successful deployments return a bounded planned 503 window instead of serving an inconsistent
  release.
- Every deployment includes a fixed drain delay calibrated to the app's longest synchronous
  request. It reduces mixed-release exposure but cannot guarantee completion of a request that
  outlives the configured interval.
- A failed or manually cancelled deployment may remain fully unavailable until rerun or manual
  recovery; this is intentional fail-closed behavior and requires the documented runbook.
- An empty target and a target left partially transferred can both re-enter the normal rsync repair
  path without booting their current application.
- `/up` intentionally returns 503 during deployment; health monitoring therefore observes the
  planned outage instead of a misleading application-level success.
- Release health verification states three different guarantees, and they must not be conflated.
  **Reachability** covers the rewrite, PHP-FPM, and bootstrap path. **Asset presence** covers the
  built assets a browser will request. **Release identity** covers only this: a PHP response
  produced by a front controller compiled from this deploy attempt's bytes returned this deploy
  attempt's identifier. It does not prove every deployed PHP file is fresh, does not prove every
  FastCGI process is fresh — the probe reaches one of them — and is not an atomic switch. Its
  soundness rests on the verified OPcache precondition: a match is only accepted when the runtime
  revalidates timestamps within a bounded interval and that interval has elapsed since the remote
  writes finished, which bounds how long any other process can still serve older bytes. Full
  atomicity would require versioned release directories and a switch, which stays deferred.
- The bounded maximum for the revalidation interval is chosen so the settle wait plus the
  remaining attempts still fit inside the existing retry window; a larger configured interval is
  rejected rather than waited out, because the workflow cannot distinguish it from a runtime that
  will not converge at all.
- The identity endpoint is public and unauthenticated. It exposes the deployed commit SHA, the
  workflow run and attempt, and the runtime's OPcache revalidation directives, and it returns 503
  whenever identity cannot be established so monitoring sees the same fail-closed state the
  workflow does.
- Because both the endpoint's reachability and the OPcache precondition are discovered over HTTP
  after maintenance is lifted, enabling the gate directly in an unmeasured environment can
  withdraw an otherwise healthy release into the planned 503. The observing stage exists to pay
  that cost as a recorded observation instead of an outage, and it deliberately keeps the old
  two-probe success condition until the measurement exists. While observing, the defect this
  decision addresses is still present; that is the accepted price of measuring first.
- The identity response reports whether preloading is configured, not the configured path. The
  endpoint is unauthenticated and the decision only needs the presence of the setting.
- Release health verification requires a per-environment canonical HTTPS health URL,
  parser-validated before any remote change so a missing value — or one the health request itself
  could not pass, such as plain HTTP against an HTTPS-forcing `.htaccess` — cannot strand the
  site in maintenance. The probes do not follow redirects, because following them would let an
  absent build asset resolve to some other 200 response and defeat the asset check; a
  non-canonical host is therefore surfaced as a redirect hint rather than silently accepted. A verification false negative
  (for example runner egress failure) withdraws a healthy release; recovery is rerunning the
  workflow.
- The planned 503 has no inquiry ID because it bypasses application bootstrap by design.
- Delivery changes remain hybrid under [`TADR-0003`](0003-shared-surface-convergence.md): derived
  applications adopt the lifecycle while preserving their app-specific rsync exclusions and drain
  duration.

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
maintenance exclusions or bypass behavior for a planned deployment.

**Ordering.** After the standalone response is active and before destructive rsync, wait a bounded,
app-calibrated drain interval exceeding the longest synchronous request; shared hosting cannot count
active requests, so this is a time-based approximation. Preserve `vendor`,
`storage/framework/down`, and `storage/framework/maintenance.php` during rsync. Run Composer
install, cache rebuilds, and migrations while the standalone response is active. Run `artisan up`
only after every post-deploy command succeeds, then remove the standalone files independently of the
configured maintenance driver and verify their removal. On failure or cancellation, keep maintenance
active until a corrected workflow completes or an operator verifies a coherent release manually.

**Release identity.** Stamp a deploy-attempt identity into the deployment package before rsync, as a
PHP literal in the front controller every request executes. The identity combines the commit SHA
with the workflow run and attempt, so redeploying the same commit does not accept the previous
attempt's response. Never resolve it from a file the application re-reads at request time: stale
bytecode can read a fresh data file and report an identity it is not running.

**Verification.** After maintenance is lifted, under enforcement require three distinct HTTP
observations before calling the deployment successful: **reachability** (the public health endpoint
returns 200), **asset presence** (a built asset referenced by the deployed Vite manifest is served),
and **release identity** (a PHP-served endpoint returns this deploy attempt's identity). `artisan
up` proves CLI bootstrap only; the health endpoint carries no identity and does not load built
assets; the asset is static and an unchanged frontend produces the previous release's file name.
Only the third observation distinguishes "some release answered" from "this release answered".
Under enforcement a mismatch never succeeds: it keeps retrying and then fails the verification. An
observing environment succeeds on the first two observations alone.

**OPcache precondition.** Read the web runtime's OPcache revalidation directives from that same
response and treat them as a precondition rather than an observation. A response is authoritative
only when it carries this deploy attempt's identity **and** an OPcache payload whose structure
matches the current schema. The identity is a compile-time constant, so stale bytecode can return
the new identity while still emitting the previous release's payload shape; keying authority on the
payload's own structure works because one method in one file produces it, making its shape a
per-file-atomic signal of which release's bytecode built it, whereas a version field in another file
could be revalidated out of step. A response with an older identity or a payload missing current
keys is treated as "not switched over yet" and retried, so changing the response format cannot fail
a deployment before the new release answers. Timestamp revalidation must be enabled, application
preloading unused, and the revalidation interval interpretable and within a bounded maximum; the
workflow waits out that interval, measured from completion of the remote writes, before accepting a
match. Uninterpretable values, values differing between responses, and configurations that never
revalidate all fail closed under enforcement, and are recorded as warnings while observing.

**Staged adoption.** An environment whose web SAPI has not been measured adopts the identity gate in
an observing stage first, where identity and the runtime directives are recorded but the deployment
still succeeds on reachability and asset presence alone. Observing must not fail on identity: the
new endpoint may be invisible behind the previous release's bytecode — exactly what happens on the
runtimes worth measuring — so an identity-failing observation stage would withdraw healthy releases
before anything is measured. The directives cannot be predicted from another environment or from
the CLI, so this applies to every new environment, not only to the initial rollout.

The stage is therefore per-environment configuration rather than a value committed to the
deployment workflow, and the workflow observes unless the configuration explicitly enforces. A
committed value would serve two roles at once — the runtime setting for the template's own
environments and the initial setting inherited by every newly derived application — and an
unmeasured environment that starts out enforcing withdraws its first healthy release into the
planned 503.

The configured value must name the repository, its immutable identifier, and the environment it
applies to, because the CI platform merges its organization, repository, and environment scopes
into one lookup and does not reveal which scope a value came from. Naming the environment alone is
insufficient: one value at the broadest scope would then enforce every application using that
environment name, which is exactly the unmeasured-application case this decision exists to prevent.
The repository name alone is also insufficient, because renaming a repository releases its name for
reuse. Both identifiers are available to the workflow expression itself, so the check costs no API
call, no additional token permission, and no new failure mode in a fail-closed path.

This bounds blast radius rather than detecting scope, and the resulting guarantee is narrow: a
stray broad-scope value cannot reach a *different* application, but within one repository a
same-named value at a broader scope shadows the per-environment one, because the platform
documents that environment-level variables do not overwrite values already resolved into the
workflow's variable context. Shadowing does not have a fixed safe direction: a broader value that
names this deployment enforces regardless of what the environment says, so clearing an
environment's variable does not necessarily return it to observing and an environment can enforce
without being measured. Defining this variable at exactly one scope is therefore a precondition of
the staged adoption, not a preference. The gate also cannot notice that an
environment now points at a different host or a changed PHP configuration; re-measuring after such
a change is an operational obligation, not something the deployment can detect. Every deployment
therefore records its effective stage, and a value folded to observing because it named another
target is reported rather than silently ignored.

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
- **Treat a health 200 plus an asset 200 as success.** Rejected: neither response carries release
  identity, so a stale web OPcache entry could end the workflow successfully while old code serves
  against the new schema, and withdrawal would never run.
- **Publish the release identity from a data file the request reads.** Rejected: old bytecode can
  read the new file and report an identity it is not executing, converting a fail-closed check into
  a false positive.
- **Extend the retry window instead of checking revalidation settings.** Rejected: a runtime that
  never revalidates never converges. The failure mode is permanence, not slowness.
- **Restart PHP-FPM or reset OPcache after deployment.** Rejected: shared hosting grants neither.
- **Serve the identity endpoint behind a shared secret.** Rejected for now: it adds a
  per-environment secret to provision before any deployment can succeed, and the exposed value is a
  deploy-attempt identifier for a private repository.
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
- The three guarantees must not be conflated. Release identity covers only this: a PHP response
  produced by a front controller compiled from this deploy attempt's bytes returned this attempt's
  identifier. It does not prove every deployed PHP file is fresh, does not prove every FastCGI
  process is fresh — the probe reaches one of them — and is not an atomic switch. Its soundness
  rests on the verified OPcache precondition, which bounds how long another process can still serve
  older bytes. Full atomicity would require versioned release directories and a switch.
- The bounded maximum for the revalidation interval is chosen so the settle wait plus the remaining
  attempts fit inside the retry window; a larger configured interval is rejected rather than waited
  out, because the workflow cannot distinguish it from a runtime that will not converge.
- The identity endpoint is public and unauthenticated. It exposes the deployed commit SHA, the
  workflow run and attempt, and the runtime's whole OPcache revalidation snapshot — whether the
  extension is loaded and enabled, whether timestamps are validated, the revalidation interval, and
  whether preloading is configured (the presence of the setting only, never the configured path).
  It returns 503 whenever identity cannot be established, so monitoring sees the same fail-closed
  state the workflow does.
- Verification requires a per-environment canonical HTTPS health URL, parser-validated before any
  remote change so a missing or unusable value cannot strand the site in maintenance. The probes do
  not follow redirects, because following them would let an absent build asset resolve to some other
  200 response and defeat the asset check. A verification false negative (for example runner egress
  failure) withdraws a healthy release; recovery is rerunning the workflow.
- While an environment is observing, the defect this decision addresses is still present there. That
  is the accepted price of measuring a runtime before gating on it.
- Delivery changes remain hybrid under [`TADR-0003`](0003-shared-surface-convergence.md): derived
  applications adopt the lifecycle while preserving their app-specific rsync exclusions, drain
  duration, and per-environment adoption stage.

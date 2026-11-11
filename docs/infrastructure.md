# External infrastructure state

Production facts outside repository configuration and the constraints they impose.

## XServer toolchain and web layers

- **Composer PHARs**: provisioned manually at `~/opt/bin/composer-<version>` in Develop and
  Production before repository configuration selects that version; `delivery` verifies the binary
  and its version but does not install it.
- **Web layers**: vendor-managed nginx fronts Apache (`.htaccess`). Production web-layer behavior
  belongs in `.htaccess`; `.docker/nginx/` is local only.

## Server environment settings

Last confirmed: October 2026, on Develop and Production of every app then derived from the template.
Delivery excludes `.env` and `.user.ini` and never writes them, so set these before an environment's
first deploy. A later `.env` change takes effect on the next deploy's `config:cache`; PHP settings
follow the server's own reload.

| Setting                 | Value                        | Reason                                                                                                          |
| ----------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`               | `production` or `develop`    | Production-only behavior and the debug check key on these names.                                                |
| `APP_DEBUG`             | `false`                      | Boot refuses `true` in both environments, because error pages would expose environment values and traces.       |
| `APP_URL`               | canonical HTTPS origin       | Trusted hosts and generated links come from it; another host serving the app must be added to `trustHosts`.     |
| `SESSION_SECURE_COOKIE` | `true`                       | Pins the `Secure` flag independently of HTTPS detection; an empty value turns it off.                           |
| `LOG_LEVEL`             | `info`                       | `debug` is local-only (`docs/logging-guidelines.md`).                                                           |
| PHP `display_errors`    | `Off`, per domain on XServer | Errors raised before Laravel handles them, such as at startup, reach the response where `APP_DEBUG` cannot act. |

## Scheduler

Last confirmed: pending (scheduler registration and host cron timezone in Develop and Production).

Required manual cron entry for each environment, replacing direct `cache:prune-expired` execution
after the schedule definitions are deployed:

```cron
0 * * * * cd "<deploy-directory>" && /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>&1
```

Task constraints: [TADR-0017](adr/template/0017-hourly-scheduler.md).

## Response header size

Last measured: October 2026, on one Develop domain. Laravel's `AddLinkHeadersForPreloadedAssets` put
every Vite preload, each with its CSP nonce, on one 8–9 KB `Link` line, and some pages then failed
every time with a vendor-layer 500 (`Premature end of script headers` in the Apache log, nothing in
the Laravel log). The cause was not confirmed, so treat any header line beyond 8 KB as unsafe.

The template omits that middleware: `@vite` already emits `<link rel="modulepreload">` tags, and the
hosting sends no `103 Early Hints` a `Link` header could feed. Recheck after adding a CDN that turns
`Link` headers into Early Hints.

## Client address and HTTPS

Last measured: October 2026, on one production domain. Without trusted proxies, `$request->ip()`
returned each client's public address, even for a forged `X-Forwarded-For`, and pages carried the
HSTS header the app adds only when `$request->isSecure()` holds. Per-IP rate limits therefore count
real clients, and production configures no trusted proxies. Recheck after changing the host or
adding a CDN or proxy, or when logged addresses collapse to one internal range.

## OPcache

Last measured: September 2026 (`cgi-fcgi`, PHP 8.5.9, `opcache.validate_timestamps=1`,
`opcache.revalidate_freq=2`, no preload). Delivery therefore keeps maintenance mode active through
the revalidation window; recheck after changing the host, PHP version, or `.user.ini`, or when a
deployment serves stale behavior.

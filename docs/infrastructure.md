# External infrastructure state

Production facts outside repository configuration and the constraints they impose.

## XServer toolchain and web layers

- **Composer PHARs**: provisioned manually at `~/opt/bin/composer-<version>` in Develop and
  Production before repository configuration selects that version; `delivery` verifies the binary
  and its version but does not install it.
- **Web layers**: Cloudflare's proxy fronts vendor-managed nginx, which fronts Apache
  (`.htaccess`). Production web-layer behavior belongs in `.htaccess`; `.docker/nginx/` is local
  only.
- **Subdomain directories**: a subdomain's document root, Develop's included, sits inside
  Production's and inherits its `.htaccess` authorization unless its own authorizes requests, so a
  subdomain without one is served only through the zone with Production's secret header.

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

## Cloudflare

Required on Develop and Production ([TADR-0019](adr/template/0019-cloudflare-in-front-of-xserver.md)),
all on the free plans. Delivery refuses to deploy when it observes one of these missing, but sees
only the paths it requests on `HEALTHCHECK_URL`'s host, and not the visitor IP settings, Bot Fight
Mode, or Always Use HTTPS behind Access.

| Setting                                                                                                                     | Value                                                                                                                                                     | Reason                                                                                                                  |
| --------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| DNS records of the environment's hosts                                                                                      | Proxied                                                                                                                                                   | The origin refuses requests without the zone's header.                                                                  |
| SSL/TLS encryption mode                                                                                                     | Full (strict), and no Configuration Rule lowers it for the hosts                                                                                          | Flexible sends the secret over plain HTTP, and Full sends it to any certificate.                                        |
| Always Use HTTPS                                                                                                            | On                                                                                                                                                        | The origin refuses plain HTTP requests, which carry no secret, before its own redirect.                                 |
| Request Header Transform Rule                                                                                               | For `(http.host in {<the environment's hosts>} and ssl)`, set static `X-Cloudflare-Proxy-Secret` to the environment's secret                              | Each `.htaccess` accepts only the SHA-1 of its own environment's secret.                                                |
| XServer サーバーキャッシュ and Xアクセラレータ                                                                              | Off for both environments' domains, サーバーキャッシュ first                                                                                              | XServer warns that its cache can serve content `.htaccess` restricts to anyone; Cloudflare caches static files instead. |
| Pseudo IPv4                                                                                                                 | Not "Overwrite headers"                                                                                                                                   | It replaces `CF-Connecting-IP` with an address that is not the visitor's.                                               |
| Managed Transform "Remove visitor IP headers"                                                                               | Off                                                                                                                                                       | It removes `CF-Connecting-IP`, so per-IP limits count Cloudflare's edges.                                               |
| Bot Fight Mode                                                                                                              | Off                                                                                                                                                       | It can challenge delivery's checks, and rules cannot exempt them.                                                       |
| Access application (Develop)                                                                                                | Every host the Transform Rule covers, with an Allow policy for its users and a Service Auth policy for delivery, and no application for a path under them | A covered host outside Access serves files to anyone, at least while a deploy replaces them.                            |
| Access Managed OAuth, 401 Response for Service Auth policies, and the account's Return 401 response for non-browser traffic | Off                                                                                                                                                       | Delivery expects anonymous requests to redirect to the login, which each replaces with a 401.                           |

GitHub environment secrets: `CLOUDFLARE_PROXY_SECRET` (`openssl rand -hex 32`, separate per
environment) in both, and the Access service token as `CLOUDFLARE_ACCESS_CLIENT_ID` and
`CLOUDFLARE_ACCESS_CLIENT_SECRET` in Develop. Production gets the token and the Access application
only while it stays private before launch.

Set up or migrate an environment in this order, Develop first, so no release is ever unprotected:

1. With the records still DNS only, confirm that XServer serves HTTPS for the hosts. Turn off
   サーバーキャッシュ and Xアクセラレータ, and set Full (strict) and Always Use HTTPS. Then add the
   Transform Rule and proxy the DNS records.
2. For an Access environment, add the application and the token secrets, confirm in Zero Trust that
   no other application, Bypass policy, or public path override in the application's destinations
   covers a path under the host.
3. Add `CLOUDFLARE_PROXY_SECRET`, without which delivery refuses to deploy, and deploy. Confirm
   that a logged request records the visitor's address and that the environment's other hosts, such
   as `www`, still redirect to the canonical one.

To rotate a secret, update the GitHub secret and the Transform Rule, then run `delivery` with
`workflow_dispatch`; every request is refused until its `rsync` step replaces `.htaccess`, which until
then still accepts the old secret, so close the origin first if it leaked.

To close the origin, for a leaked secret or for a live `.htaccess` that broke and fails delivery's
checks too, replace that `.htaccess` over SSH with only `Require all denied`; the checks' own
`.htaccess` overrides it, so `delivery` for the environment it belongs to can still deploy.

## Scheduler

Last confirmed: November 2026, on Develop and Production of every app then derived from the template.

The only manual cron entry the template requires in each environment, replacing direct
`cache:prune-expired` execution after the schedule definitions are deployed:

```cron
0 * * * * cd "<deploy-directory>" && /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>&1
```

Task constraints: [TADR-0017](adr/template/0017-hourly-scheduler.md). Apps add their own entries
beside this one for tasks those constraints exclude.

XServer's cron memo field (up to 127 full-width / 255 half-width characters) shares one list across
every site on the account. Prefix memos with the app and environment, and point the scheduler memo
to `routes/console.php` rather than listing tasks so schedule changes do not leave it stale
(Japanese example: `<app> 本番|開発: Laravel スケジューラ（毎時0分）。実行内容は routes/console.php`).

## Response header size

Last measured: October 2026, on one Develop domain. Laravel's `AddLinkHeadersForPreloadedAssets` put
every Vite preload, each with its CSP nonce, on one 8–9 KB `Link` line, and some pages then failed
every time with a vendor-layer 500 (`Premature end of script headers` in the Apache log, nothing in
the Laravel log). The cause was not confirmed, so treat any header line beyond 8 KB as unsafe.

The template omits that middleware: `@vite` already emits `<link rel="modulepreload">` tags, and the
hosting sends no `103 Early Hints` a `Link` header could feed. Cloudflare can turn `Link` headers
into Early Hints, so recheck before sending any.

## Client address and HTTPS

Last measured: October 2026, on one production domain before Cloudflare. Without trusted proxies,
`$request->ip()` returned the connecting address, even for a forged `X-Forwarded-For`, and pages
carried the HSTS header the app adds only when `$request->isSecure()` holds. Behind Cloudflare the
connecting address is Cloudflare's edge ([TADR-0019](adr/template/0019-cloudflare-in-front-of-xserver.md)).
Recheck after changing the host, or when logged addresses collapse to Cloudflare's or one internal
range.

## OPcache

Last measured: September 2026 (`cgi-fcgi`, PHP 8.5.9, `opcache.validate_timestamps=1`,
`opcache.revalidate_freq=2`, no preload). Delivery therefore keeps maintenance mode active through
the revalidation window; recheck after changing the host, PHP version, or `.user.ini`, or when a
deployment serves stale behavior.

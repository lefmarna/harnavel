# External infrastructure state

Production facts outside repository configuration and the constraints they impose.

## XServer toolchain and web layers

- **Composer PHARs**: provisioned manually at `~/opt/bin/composer-<version>` in Develop and
  Production before repository configuration selects that version; `delivery` verifies the binary
  and version without installing it.
- **Web layers**: Cloudflare's proxy fronts vendor-managed nginx, which fronts Apache
  (`.htaccess`). Production web-layer behavior belongs in `.htaccess`; `.docker/nginx/` is local
  only.
- **Subdomain directories**: a subdomain's document root (Develop included) sits inside
  Production's and inherits its `.htaccess` authorization unless its own authorizes requests.

## Server environment settings

Delivery excludes `.env` and `.user.ini` and never writes them, so set these before an environment's
first deploy. Later `.env` changes take effect on the next deploy's `config:cache`.

| Setting                 | Value                        | Reason                                                                                                          |
| ----------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`               | `production` or `develop`    | Production-only behavior and the debug check key on these names.                                                |
| `APP_DEBUG`             | `false`                      | Boot refuses `true` in both environments, because error pages would expose environment values and traces.       |
| `APP_URL`               | canonical HTTPS origin       | Trusted hosts and generated links come from it; another host serving the app must be added to `trustHosts`.     |
| `SESSION_SECURE_COOKIE` | `true`                       | Pins the `Secure` flag independently of HTTPS detection; an empty value turns it off.                           |
| `LOG_LEVEL`             | `info`                       | `debug` is local-only (`docs/logging-guidelines.md`).                                                           |
| PHP `display_errors`    | `Off`, per domain on XServer | Errors raised before Laravel handles them, such as at startup, reach the response where `APP_DEBUG` cannot act. |

## Cloudflare

Required on Develop and Production ([TADR-0019](adr/template/0019-cloudflare-in-front-of-xserver.md))
on the Free and Zero Trust Free plans (Local does not use Cloudflare); optional WAF Custom Rules,
Rate Limiting, and app-specific cache rules stay app-owned. Delivery checks what it can on
`HEALTHCHECK_URL`'s host, but cannot see SSL/TLS mode (`Full (strict)` vs `Full`, or Configuration
Rule overrides), visitor IP settings, Bot Fight Mode, Always Use HTTPS behind Access, alternate
hosts (such as `www`), or the ACME Bypass application. Step-by-step dashboard setup, SSL
issuance/recovery, and verification procedures live in the
[Wiki](https://github.com/lefmarna/harnavel/wiki).

| Setting                                                                                                                     | Value                                                                                                                                                                                           | Reason                                                                                                                                                      |
| --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| DNS records of the environment's hosts                                                                                      | Always Proxied (apex or first-level subdomains)                                                                                                                                                 | The origin refuses requests without the zone's header; never switch to DNS only for SSL issuance or renewal. Free Universal SSL excludes deeper subdomains. |
| Origin SSL certificate                                                                                                      | XServer 無料独自SSL (initial issuance and post-expiry recovery via 他社ネームサーバーでのDNS認証; routine renewal via ACME HTTP-01)                                                             | Provides the valid origin certificate Full (strict) requires while keeping DNS Proxied and without paid add-ons.                                            |
| SSL/TLS encryption mode                                                                                                     | Full (strict), and no Configuration Rule lowers it for the hosts                                                                                                                                | Flexible sends the secret over plain HTTP, and Full sends it to any certificate.                                                                            |
| Always Use HTTPS                                                                                                            | On                                                                                                                                                                                              | The origin refuses plain HTTP requests, which carry no secret, before its own redirect.                                                                     |
| Request Header Transform Rule                                                                                               | Per environment, for `(http.host in {<the environment's hosts>} and ssl)`, set static `X-Cloudflare-Proxy-Secret` to the environment's secret                                                   | Each `.htaccess` accepts only the SHA-1 of its own environment's secret; Develop and Production never share a secret.                                       |
| XServer サーバーキャッシュ and Xアクセラレータ                                                                              | Off for both environments' domains, サーバーキャッシュ first                                                                                                                                    | XServer warns that its cache can serve content `.htaccess` restricts to anyone; Cloudflare caches static files instead.                                     |
| Pseudo IPv4                                                                                                                 | Not "Overwrite headers"                                                                                                                                                                         | It replaces `CF-Connecting-IP` with an address that is not the visitor's.                                                                                   |
| Managed Transform "Remove visitor IP headers"                                                                               | Off                                                                                                                                                                                             | It removes `CF-Connecting-IP`, so per-IP limits count Cloudflare's edges.                                                                                   |
| Bot Fight Mode                                                                                                              | Off                                                                                                                                                                                             | It can challenge delivery's checks, and rules cannot exempt them.                                                                                           |
| Access application (Develop; optional on pre-launch Production)                                                             | Every host the Transform Rule covers, with an Allow policy for its users and a Service Auth policy for delivery; public Production uses no Access in principle                                  | A covered host outside Access serves files to anyone, at least while a deploy replaces them; `/up` stays behind Access and is never bypassed.               |
| ACME challenge Access exception (Access-protected hosts only)                                                               | Dedicated Access application scoped strictly to `/.well-known/acme-challenge/*` with a Bypass (`Include Everyone`) policy; no other Bypass, path application, or public override under the host | Allows XServer free SSL HTTP-01 validation without disabling proxy or Access; never widen to `/.well-known/*` or bypass `/up`.                              |
| Access Managed OAuth, 401 Response for Service Auth policies, and the account's Return 401 response for non-browser traffic | Off                                                                                                                                                                                             | Delivery expects anonymous requests to redirect to the login, which each replaces with a 401.                                                               |

GitHub environment secrets (separate per environment): `HEALTHCHECK_URL` (`https://<host>/up`),
`CLOUDFLARE_PROXY_SECRET` (generate with `openssl rand -hex 32`), and the Access service token
(`CLOUDFLARE_ACCESS_CLIENT_ID` / `CLOUDFLARE_ACCESS_CLIENT_SECRET`) in Develop (and Production only
while Access is enabled pre-launch).

Setup order (Develop first, so no private release is exposed during new setup or migration):

1. Prepare origin and Cloudflare settings (keep web DNS records Proxied; never switch to DNS only):
   - On an existing private/Develop site being migrated, keep existing access restrictions or
     temporarily close the origin (`.htaccess` `Require all denied`). Origin secret protection
     takes effect only once `delivery` deploys the new `.htaccess`, and `delivery`'s pre-check
     directory overrides root `Require all denied`.
   - Issue XServer 無料独自SSL via 他社ネームサーバーでのDNS認証, disable サーバーキャッシュ and
     Xアクセラレータ, enable Full (strict) and Always Use HTTPS, and add the Transform Rule.
   - For Access-protected environments, configure the host Access application (Allow + Service Auth)
     and the dedicated `/.well-known/acme-challenge/*` Bypass (`Include Everyone`) application
     before routing traffic to the environment (and before lifting any legacy origin restriction),
     and verify in Zero Trust that no other application, Bypass policy, or public path override
     covers paths under the host (especially `/up`).
2. Ensure web DNS records are Proxied, add `CLOUDFLARE_PROXY_SECRET`, `HEALTHCHECK_URL`, and (where
   Access is used) the service token secrets, then run `delivery`. `delivery` validates routing,
   Access redirects, and secret handling before replacing `.htaccess` (which activates origin
   protection and lifts any temporary closure). Confirm logs record visitor IPs, alternate hosts
   (such as `www`) redirect to the canonical host, and `/.well-known/acme-challenge/` is reachable.

**SSL certificate issuance, renewal, and recovery**: initial issuance and post-expiry recovery use
XServer's 他社ネームサーバーでのDNS認証 while keeping web DNS records Proxied and Full (strict)
enabled; routine auto-renewal uses the ACME HTTP-01 path. If DNS validation fails, verify the DNS
records and validation conditions rather than disabling Proxy or lowering Full (strict). XServer
does not officially guarantee auto-renewal behind Cloudflare's proxy, so monitor certificate expiry
and confirm renewal results in each environment as routine operations; see the
[Wiki](https://github.com/lefmarna/harnavel/wiki) for dashboard steps.

**Secret rotation and emergency origin closure**: to rotate a secret, update the GitHub secret and
Transform Rule, then run `delivery` via `workflow_dispatch`; close the origin first if leaked, since
the previous `.htaccess` accepts the old secret until `rsync` replaces it. To close the origin (on a
leak or broken `.htaccess`), replace `.htaccess` over SSH with `Require all denied`; delivery's
pre-check directory overrides it, so `delivery` can still deploy.

## Scheduler

The template requires one cron entry per environment:

```cron
0 * * * * cd "<deploy-directory>" && /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>&1
```

Task constraints: [TADR-0017](adr/template/0017-hourly-scheduler.md). XServer shares one cron memo
list across the account (up to 127 full-width / 255 half-width chars); prefix memos with app and
environment and point to `routes/console.php` rather than listing tasks (e.g.,
`<app> 本番|開発: Laravel スケジューラ（毎時0分）。実行内容は routes/console.php`).

## Host runtime constraints

- **Response header size**: an 8–9 KB `Link` header from `AddLinkHeadersForPreloadedAssets` caused
  vendor-layer 500s (`Premature end of script headers`, nothing in Laravel logs). Treat any header
  line over 8 KB as unsafe. `@vite` already emits `<link rel="modulepreload">` tags, and Cloudflare
  can convert `Link` headers to Early Hints, so recheck before sending any.
- **Client address and HTTPS**: behind Cloudflare, XServer sees Cloudflare's edge IP and HTTPS
  terminates over Full (strict) ([TADR-0019](adr/template/0019-cloudflare-in-front-of-xserver.md)).
  Recheck after changing the host or if logged addresses collapse to Cloudflare or internal ranges.
- **OPcache**: XServer runs `cgi-fcgi` with `opcache.validate_timestamps=1`,
  `opcache.revalidate_freq=2`, and no preload. Delivery keeps maintenance mode active across that
  revalidation window; recheck after changing the host, PHP version, or `.user.ini`, or if deploys
  serve stale code.

# External infrastructure state

This file records production facts that are not represented by repository configuration and the
constraints they impose.

## XServer toolchain

Composer PHARs are provisioned manually, outside the repository. A matching PHAR must exist in
Develop and Production before repository configuration selects that version. The delivery workflow
verifies the remote PHAR and its version but does not provision it.

## Web server layers

A vendor-managed nginx layer fronts an Apache layer that reads `.htaccess`. Production behavior that
needs a server layer therefore belongs in `.htaccess`; the nginx configuration in this repository is
local only.

## Server environment settings

Last confirmed: October 2026, `SESSION_SECURE_COOKIE`, `LOG_LEVEL`, and `display_errors` on Develop
and Production of every app then derived from the template.

Each environment keeps its `.env` and PHP settings on the server. Delivery excludes `.env` and
`.user.ini` and never writes them, so an app derived from the template, or a new environment of one,
needs these set before its first deploy. A later `.env` change takes effect on the next deploy,
which runs `config:cache`. PHP reads its own settings without a deploy, so `display_errors` follows
the server's reload of them instead.

| Setting                 | Value                        | Reason                                                                                                                                                                       |
| ----------------------- | ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`               | `production` or `develop`    | Production-only behavior and the debug check below key on these names.                                                                                                       |
| `APP_DEBUG`             | `false`                      | The app refuses to boot with it on in either environment, because error pages would expose environment values and stack traces.                                              |
| `APP_URL`               | the canonical HTTPS origin   | Trusted hosts and generated links come from it (TADR-0016); a host serving the app under another name must be added to `trustHosts`.                                         |
| `SESSION_SECURE_COOKIE` | `true`                       | Left unset, the Secure flag follows the HTTPS detection described in "Client address and HTTPS"; `true` keeps it independent of the layers, and an empty value turns it off. |
| `LOG_LEVEL`             | `info`                       | `debug` is development-only (`docs/logging-guidelines.md`); `.env.example` uses it for local work.                                                                           |
| PHP `display_errors`    | `Off`, per domain on XServer | Errors raised before Laravel handles them, such as at startup, reach the response otherwise; `APP_DEBUG` does not cover them.                                                |

## Response header size

Last measured: October 2026, on one Develop domain.

Laravel's `AddLinkHeadersForPreloadedAssets` put every Vite preload of a page, each with its CSP
nonce, on one `Link` line of 8 to 9 KB. A page whose line was 8,200 bytes failed every time with a
500 from the vendor layer, `Premature end of script headers` in the Apache error log and nothing in
the Laravel log, while the PHP CLI on the same server built the same response normally. Pages with
8,499- and 8,654-byte lines returned 200, so the outcome depends on more than the length; one
explanation that fits is a split at 8,192 bytes whose remainder parses as a header only when it holds
a colon. The handler and its parsing were not confirmed, so treat any header line beyond 8 KB as
unsafe.

The template therefore does not use that middleware. The `<link rel="modulepreload">` tags that
`@vite` emits in the document head already cover preloading, and the hosting sends no
`103 Early Hints` that a `Link` header could feed. Recheck after adding a CDN that turns `Link`
headers into Early Hints.

## Client address and HTTPS

Last measured: October 2026, on one production domain.

Without any trusted proxy configured, `$request->ip()` returned each client's public address: logged
addresses spread across many public ranges, and a request carrying a forged `X-Forwarded-For` was
logged with the sender's real address. Pages carried the `Strict-Transport-Security` header that the
app adds only when `$request->isSecure()` holds, while static files served without PHP did not, so
the vendor layers report HTTPS to PHP. Per-IP rate limits therefore count real clients, and
production configures no trusted proxies.

Recheck this assumption after changing the hosting provider or adding a CDN or another proxy in
front of the site, or when logged addresses collapse to one internal range.

## OPcache

Last measured: September 2026.

Two production domains and one development subdomain across two applications reported web SAPI
`cgi-fcgi`, PHP 8.5.9, `opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`, and no preload.
This is why delivery keeps maintenance active through an OPcache revalidation window.

Recheck this assumption after changing the hosting provider, PHP version, or server-side `.user.ini`,
or when a deployment serves stale behavior.

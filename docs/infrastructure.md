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

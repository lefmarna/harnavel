# Cloudflare in front of XServer

Develop and Production serve application traffic only through Cloudflare's proxy on the Free and
Zero Trust Free plans, with no paid add-ons and no non-Cloudflare fallback (Local does not use
Cloudflare). Origin protection and user authentication stay separate: the origin admits only its own
zone's traffic (except `/.well-known/acme-challenge/*` for XServer certificate validation), and
Cloudflare Access replaces Basic auth for human access to Develop (and optionally pre-launch
Production; public Production does not use Access in principle).

A per-environment zone Transform Rule injects `X-Cloudflare-Proxy-Secret` on HTTPS requests to prove
zone origin. Cloudflare Tunnel (needs a resident process), Authenticated Origin Pulls (unsupported
on XServer's TLS terminator), IP allowlisting (admits other Cloudflare tenants), and Laravel
middleware or `.env`-based secret checks (cannot protect static files or pre-PHP requests) were
rejected. Apache enforces the secret's SHA-1 via an authorization rule so subdirectories with their
own rewrites inherit it, exempting only `/.well-known/acme-challenge/*` for XServer certificate
validation. Because Apache cannot strip the header before internal redirects re-authorize,
`public/index.php` drops it from `$_SERVER`/`$_ENV` (under FastCGI `getenv()` remains reachable only
to code that can already read `.env`).

Web DNS records stay Proxied at all times, and Cloudflare uses Full (strict) mode with Always Use
HTTPS enabled so the secret is sent only over HTTPS to a valid XServer free custom SSL certificate,
while forwarded headers stay untrusted. Initial XServer free SSL issuance and post-expiry recovery
use XServer's DNS validation for external nameservers (`他社ネームサーバーでのDNS認証`), while
routine auto-renewal uses the ACME HTTP-01 path (with a dedicated `/.well-known/acme-challenge/*`
Bypass (`Include Everyone`) application as the sole Access exception on Access-protected hosts so
`/up` and other paths stay protected). Switching Proxy off for issuance or renewal, periodic proxy
toggling, lowering Full (strict) to Full or Flexible, and custom cron or unofficial API renewal
automation were rejected. Because XServer does not officially guarantee auto-renewal behind
Cloudflare's proxy and a lapsed certificate breaks Full (strict), expiry monitoring and renewal
verification in each environment remain routine operational responsibilities.

Shared hosting limits network-level origin isolation, and the origin does not verify Access JWTs
because the zone secret already restricts origin access and JWT checks cannot cover static files
while adding key fetches and per-app audience config; a leaked Develop secret therefore bypasses
Access. `public/index.php` trusts `CF-Connecting-IP` only when the request's secret matches the
deployed SHA-1, preventing IP spoofing even if `.htaccess` authorization fails.

Delivery checks `/up` through Access with a service token rather than a bypass, and validates
Cloudflare routing and `.htaccess` rules in a temporary directory before replacing release files.
`public/.htaccess` redirects direct `/public/...` paths so all traffic passes root host rules.
Optional WAF Custom Rules, Rate Limiting, and app-specific cache rules stay app-owned.

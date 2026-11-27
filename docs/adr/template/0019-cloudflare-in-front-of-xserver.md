# Cloudflare in front of XServer

Develop and Production are reachable only through Cloudflare's proxy on the Free and Zero Trust Free
plans, with no paid add-ons and no non-Cloudflare fallback (Local does not use Cloudflare). Origin
protection and user authentication stay separate: the origin admits only its own zone's traffic, and
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

Cloudflare uses Full (strict) mode with Always Use HTTPS enabled so the secret is sent only over
HTTPS to a valid XServer free custom SSL certificate, and forwarded headers stay untrusted. On
Access-protected hosts, a dedicated `/.well-known/acme-challenge/*` Bypass (`Include Everyone`)
application is the sole Access exception so ACME HTTP-01 validation reaches the origin without
exposing `/up` or other paths. Because XServer free SSL auto-renewal (and issuance while a valid
origin certificate is present) can work behind proxied DNS and Full (strict) with this exception,
periodic proxy toggling, lowering Full (strict), and custom cron or unofficial API renewal
automation were rejected. Initial issuance without a valid origin certificate (handled while DNS is
unproxied) and recovery after expiry cannot rely on Full (strict) HTTPS forwarding; because XServer
does not officially guarantee auto-renewal behind Cloudflare's proxy, expiry monitoring and renewal
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

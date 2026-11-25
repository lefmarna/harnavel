# Cloudflare in front of XServer

Develop and Production are reachable only through Cloudflare's free-plan proxy. Origin protection
and user authentication stay separate: the origin admits only its own zone's traffic, and
Cloudflare Access handles human access to Develop (and pre-launch Production).

A zone Transform Rule injects a per-environment secret header on HTTPS requests to prove zone
origin. Cloudflare Tunnel (needs a resident process), Authenticated Origin Pulls (unsupported on
XServer's TLS terminator), and IP allowlisting (admits other Cloudflare tenants) were all rejected.
Apache enforces the secret's SHA-1 via an authorization rule so subdirectories with their own
rewrites inherit it, exempting only ACME challenges for XServer certificate validation. Because
Apache cannot strip the header before internal redirects re-authorize, `public/index.php` drops it
from `$_SERVER`/`$_ENV` (under FastCGI `getenv()` remains reachable only to code that can already
read `.env`).

Cloudflare uses Full (strict) mode so the secret is sent only to a valid certificate, and forwarded
headers stay untrusted. Unresolved: XServer's free certificate auto-renewal behind a proxied DNS
record is unconfirmed, and a lapsed certificate breaks Full (strict); renewal is tracked
separately.

The origin does not verify Access JWTs because the zone secret already restricts origin access and
JWT checks cannot cover static files while adding key fetches and per-app audience config; a leaked
Develop secret therefore bypasses Access. `public/index.php` trusts `CF-Connecting-IP` only when
the request's secret matches the deployed SHA-1, preventing IP spoofing even if `.htaccess`
authorization fails.

Delivery checks health through Access with a service token rather than a bypass, and validates
Cloudflare routing and `.htaccess` rules in a temporary directory before replacing release files.
`public/.htaccess` redirects direct `/public/...` paths so all traffic passes root host rules.

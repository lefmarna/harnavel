# Cloudflare in front of XServer

Develop and Production are reached only through Cloudflare's proxy on the free plan. Origin
protection and user authentication stay separate: the origin admits only its own zone's traffic, and
Cloudflare Access alone authenticates the people who may use Develop, or Production before launch.

A header that a zone Transform Rule sets on HTTPS requests proves that a request came through the
zone. Cloudflare Tunnel needs a resident process, Authenticated Origin Pulls needs
client-certificate verification that XServer's TLS terminator does not let apps configure, and an
allowlist of Cloudflare's addresses also admits every other Cloudflare customer. Apache checks the
header with an authorization rule, because a directory with its own rewrite rules, such as
`public/`, replaces the parent's, while it inherits authorization. Only ACME challenges are exempt,
so XServer's certificate validation reaches the origin without the header. `.htaccess` holds only the
secret's SHA-1, because the shared web server must read it. Apache cannot strip the header, since it
may authorize again after an internal redirect, so `public/index.php` drops it from `$_SERVER` and
`$_ENV`; under FastCGI `getenv()` still returns it, but only to code that can already read
`.env`. Each environment has its own secret, so a leak exposes only that environment.

Cloudflare connects in Full (strict) mode, so it sends the secret only to a server with a valid
certificate for the host. HTTPS comes from that connection, and forwarded headers stay untrusted.
Unresolved: XServer documents that its free certificate renews automatically only while the domain's
DNS points at XServer, which a proxied record does not, and a lapsed certificate makes Cloudflare
refuse the origin. Whether it renews behind the proxy, such as through XServer's DNS validation, is
not yet known, and how to renew it is decided separately.

The origin does not verify Access JWTs: the header check already confines the origin to the zone,
JWT checks could not cover static files, and they would add a key fetch and per-app audience
settings. A leaked Develop secret therefore bypasses Access for Develop.

`public/index.php` takes the client address from `CF-Connecting-IP` only when the request's secret
matches the SHA-1 that delivery writes into it as into `.htaccess`, so a header check that stops
applying still cannot be used to forge addresses, not even by the other customers' Workers that
Cloudflare's addresses admit.

Delivery checks health through Access with a service token rather than a bypass. Before replacing
anything, it serves files from a temporary directory laid out like the release, under its
`.htaccess` files, on `HEALTHCHECK_URL`'s host through Cloudflare and directly, so a setting that
would leave the release down or exposed stops the deploy instead. Access on paths it does not
request stays a Zero Trust setting to review. `public/.htaccess` redirects direct `/public/...`
requests to the path without `/public`, so every request passes the root's host rules and other
hosts of the directory, such as `www`, only redirect to the canonical one.

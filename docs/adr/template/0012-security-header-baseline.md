# Baseline security headers belong to the template

The template ships a small header baseline as defense in depth whose maintenance and startup
behavior would cost more to repeat in every app. External form submissions, intentional framing,
`<object>`/`<embed>` content, or `<base>` usage need an app-owned exception, kept consistent with
`X-Frame-Options`.

`script-src` uses a per-request nonce and `'strict-dynamic'` because it needs no host allowlist and
so holds for any app; it is also the
[strict CSP AdSense supports](https://support.google.com/adsense/answer/16283098?hl=en-GB), which
requires `'unsafe-eval'`. Every script tag an app adds carries the nonce. Other resource directives,
reporting, and `Permissions-Policy` stay app-owned: they need per-app allowlists and would block
styles and analytics beacons without stopping script execution. HSTS `includeSubDomains` and
`preload` stay app-owned because the template cannot guarantee HTTPS for every subdomain.

The baseline is also sent from a
[PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
registered before maintenance and autoloading, so pre-rendered maintenance and early errors carry
it. An app replaces the policy instead of adding one, because
[multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies). Apache
configuration alone was rejected: local nginx needs the same behavior, and a server-level CSP would
override app-owned policies. Static files and responses produced before PHP runs stay outside this
guarantee.

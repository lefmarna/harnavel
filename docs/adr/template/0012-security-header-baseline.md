# Baseline security headers belong to the template

The template ships a small security-header baseline
([`bootstrap/security-headers.php`](../../../bootstrap/security-headers.php)) through the PHP
entry point. The value is modest defense in depth plus shared ownership of its maintenance and
startup behavior, which costs more to repeat in every derived app. These are starter defaults, not
a compatibility promise: external form submissions, intentional framing, `<object>`/`<embed>`
content, or `<base>` usage need an app-owned exception, kept consistent with `X-Frame-Options`,
which exists only for legacy compatibility.

Global middleware extends that policy with a strict `script-src`: a per-request nonce from
`Vite::useCspNonce()` plus `'strict-dynamic'`, so only the template's own script tags and the
scripts they load run, and markup injected through an XSS does not. It needs no host allowlist, so
it holds for any app: Google Analytics loads from its nonced tag, and AdSense supports only
[this kind of strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB), which adds
`'unsafe-eval'` when a publisher ID is configured. `'unsafe-inline'` and `https:` are a fallback
for older browsers; browsers that support `'strict-dynamic'` ignore both. With `APP_DEBUG` on, the
same `script-src` is sent as Report-Only: Laravel's debug page runs scripts without the nonce and
uses `eval`, and Inertia shows an error response in an iframe that inherits the page's policy. Every
script tag an app adds needs the nonce, and E2E, which runs with debug on, fails on any
`securitypolicyviolation`, enforced or reported. Laravel's `/up` page loads Tailwind from a CDN
without the nonce and renders unstyled; health checks read only its status. Other resource
restrictions such as `default-src`, `style-src`, `img-src`, and `connect-src`, together with
Report-Only policies, reporting endpoints, and Permissions-Policy, stay app-owned because they need
allowlists that vary per app; they would block style attributes and analytics beacons without
stopping script execution. Server fingerprint suppression is outside this baseline.

Global middleware adds `Strict-Transport-Security: max-age=31536000` only outside the `local`
environment and when Laravel's `$request->isSecure()` identifies an HTTPS request, preserving an
app-supplied header. HSTS applies across ports on a host, so local HTTPS proxies must not leave a
policy that blocks HTTP development services on other ports. The template commits to HSTS for one
year on that host. `includeSubDomains` and `preload` stay app-owned because
the template cannot guarantee HTTPS for every subdomain. HSTS does not cover responses before
Laravel boots, including pre-rendered maintenance: receiving it once on a normal HTTPS response
establishes the policy, and adding it to the PHP header callback would require HTTPS detection
outside Laravel.

For the other baseline headers, a
[PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
registered before maintenance and autoloading, plus global middleware, covers
[pre-rendered maintenance](https://laravel.com/docs/13.x/configuration#pre-rendering-the-maintenance-mode-view)
and early middleware errors that an appended middleware never reaches. PHP supports one header
callback, so derived integrations must preserve this registration, and an app replaces the policy
without a conflicting fallback because
[multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies). Apache
configuration alone is rejected: local nginx needs the same behavior, and a blanket server-level
CSP would override app-owned policies. Static files and responses generated before PHP runs stay
outside this guarantee; maintenance availability stays with
[TADR-0006](0006-in-place-deployment.md).

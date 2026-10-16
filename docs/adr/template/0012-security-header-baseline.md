# Baseline security headers belong to the template

The template ships a small security-header baseline
([`bootstrap/security-headers.php`](../../../bootstrap/security-headers.php)) as defense in depth
whose maintenance and startup behavior would cost more to repeat in every app. These are starter
defaults, not a compatibility promise: external form submissions, intentional framing,
`<object>`/`<embed>` content, or `<base>` usage need an app-owned exception, kept consistent with
`X-Frame-Options`.

Global middleware adds a strict `script-src`: a per-request nonce plus `'strict-dynamic'`, so only
the template's own script tags and the scripts they load run, and markup injected through an XSS
does not. It needs no host allowlist, so it holds for any app; AdSense supports only
[this kind of strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB) and adds
`'unsafe-eval'` when a publisher ID is configured. Every script tag an app adds needs the nonce, and E2E fails on any `securitypolicyviolation`. Other resource directives, Report-Only
policies, reporting endpoints, and Permissions-Policy stay app-owned: they need per-app allowlists
and would block styles and analytics beacons without stopping script execution.

`Strict-Transport-Security: max-age=31536000` is added to HTTPS responses outside `local`, so the
template commits to HSTS for one year on that host. `includeSubDomains` and `preload` stay
app-owned because the template cannot guarantee HTTPS for every subdomain. The PHP header callback
below does not send HSTS, because it would need HTTPS detection outside Laravel; one normal HTTPS
response establishes the policy.

The other baseline headers come from a
[PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
registered before maintenance and autoloading, plus global middleware, so
[pre-rendered maintenance](https://laravel.com/docs/13.x/configuration#pre-rendering-the-maintenance-mode-view)
and early middleware errors are covered. PHP supports one header callback, so derived integrations
preserve this registration, and an app replaces the policy rather than adding a conflicting one,
because [multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies). Apache
configuration alone was rejected: local nginx needs the same behavior, and a server-level CSP would
override app-owned policies. Static files and responses produced before PHP runs stay outside this
guarantee; maintenance availability stays with [TADR-0006](0006-in-place-deployment.md).

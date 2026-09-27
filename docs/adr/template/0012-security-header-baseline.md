# Baseline security headers belong to the template

The template ships a small security-header baseline
([`bootstrap/security-headers.php`](../../../bootstrap/security-headers.php)) through the PHP
entry point. The value is modest defense in depth plus shared ownership of its maintenance and
startup behavior, which costs more to repeat in every derived app. These are starter defaults, not
a compatibility promise: external form submissions, intentional framing, `<object>`/`<embed>`
content, or `<base>` usage need an app-owned exception, kept consistent with `X-Frame-Options`,
which exists only for legacy compatibility.

Resource-loading restrictions such as `default-src`, `script-src`, and `style-src`, together with
nonces, Report-Only policies, reporting endpoints, advertising allowlists, HSTS, and
Permissions-Policy, stay app-owned because HTTPS and subdomain commitments, browser feature
requirements, and integration ownership vary per app. AdSense supports
[a documented strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB), so its
omission is about ownership, not incompatibility. Server fingerprint suppression is outside this
baseline.

A [PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
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

# Baseline security headers belong to the template

The template ships a small security-header baseline
([`bootstrap/security-headers.php`](../../../bootstrap/security-headers.php)) through the PHP
entry point. The value is modest defense in depth plus shared ownership of its maintenance and
startup behavior, which costs more to repeat in every derived app. These are starter defaults, not
a compatibility promise: external form submissions, intentional framing, `<object>`/`<embed>`
content, or `<base>` usage need an app-owned exception, kept consistent with `X-Frame-Options`,
which exists only for legacy compatibility.

Resource-loading restrictions such as `default-src`, `script-src`, and `style-src`, together with
nonces, Report-Only policies, reporting endpoints, advertising allowlists, and Permissions-Policy,
stay app-owned because browser feature requirements and integration ownership vary per app.
AdSense supports
[a documented strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB), so its
omission is about ownership, not incompatibility. Server fingerprint suppression is outside this
baseline.

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

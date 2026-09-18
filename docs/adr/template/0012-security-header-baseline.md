# Baseline security headers belong to the template

The template ships a small security-header baseline
([`bootstrap/security-headers.php`](../../../bootstrap/security-headers.php)) delivered through the
PHP entry point. The value is modest defense in depth plus shared ownership of the maintenance and
startup behavior; repeating that lifecycle work in every derived app costs more than maintaining it
once. These are starter defaults, not a compatibility promise: external form submissions,
intentional framing, `<object>`/`<embed>` content, or `<base>` usage require an app-owned exception,
kept consistent with `X-Frame-Options`, which exists only for legacy compatibility.

Resource-loading restrictions such as `default-src`, `script-src`, and `style-src`, together with
nonces, Report-Only policies, reporting endpoints, advertising allowlists, HSTS, and
Permissions-Policy, remain app-owned because HTTPS and subdomain commitments, browser feature
requirements, and integration ownership vary per app. AdSense supports
[a documented strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB), so its
omission is about ownership, not incompatibility. Server fingerprint suppression is outside this
baseline.

The entry point registers a [PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
before maintenance and autoloading that adds only the headers still missing when PHP sends the
response, and global middleware wraps the others so early error responses receive the same defaults.
[Pre-rendered maintenance](https://laravel.com/docs/13.x/configuration#pre-rendering-the-maintenance-mode-view)
and early middleware errors never reach an appended middleware, and a derived app must be able to
replace the policy without a conflicting fallback because
[multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies). PHP supports one
header callback, so derived integrations must preserve this registration. Apache configuration
alone is rejected: local nginx needs the same behavior, and a blanket server-level CSP would
override app-owned policies. Static files and responses generated before PHP runs stay outside this
guarantee. Real HTTP coverage of these paths is accepted because ordinary Feature tests cannot
establish PHP SAPI header behavior; maintenance availability guarantees stay with
[TADR-0006](0006-in-place-deployment.md).

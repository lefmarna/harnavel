# Baseline security headers belong to the template

Adopt a small security-header baseline for responses through the PHP entry point. The value is
modest defense in depth and shared ownership of maintenance and startup behavior, rather than
remediation of a demonstrated exploit. Repeating that lifecycle work in derived apps costs more
than maintaining it once.

The default policy is:

```http
Content-Security-Policy: frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'self'
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: DENY
```

These are starter defaults, not a promise of compatibility with every application. External form
submissions, intentional framing, `<object>`/`<embed>` content, or `<base>` usage require an
app-owned exception. Keep framing exceptions consistent with `X-Frame-Options`, which provides
legacy compatibility. A derived app must be able to replace its response policy without retaining
a conflicting fallback: [multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies).

Resource-loading restrictions, including `default-src`, `script-src`, and `style-src`, remain
app-owned, as do nonces, Report-Only policies, reporting endpoints, and advertising allowlists.
AdSense does support [a documented strict CSP](https://support.google.com/adsense/answer/16283098?hl=en-GB);
the reason to omit it is integration and maintenance ownership, not inherent incompatibility.
HSTS and Permissions-Policy also remain app-owned because HTTPS/subdomain commitments and browser
feature requirements vary. Server fingerprint suppression is outside this baseline.

The template owns one autoload-independent baseline and its handover to Laravel responses.
[Pre-rendered maintenance](https://laravel.com/docs/13.x/configuration#pre-rendering-the-maintenance-mode-view)
can exit before middleware runs, and early global middleware can produce error responses before
an appended middleware runs. Preserve fallback headers until a response is available, then avoid
duplicate native and response headers. Accept the cost of real HTTP coverage for these paths;
ordinary Feature tests cannot establish PHP SAPI header behavior. This does not expand the
maintenance availability guarantees in [TADR-0006](0006-in-place-deployment.md).

The entry point registers a [PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
before maintenance and autoloading, adding only headers still missing when PHP sends the response.
This avoids pre-queuing duplicate defaults and does not require `header_remove()` for handover.
Global middleware wraps the other middleware so early error responses also receive the defaults.
PHP supports only one header callback: derived integrations must preserve this registration.

Do not rely solely on Apache configuration: local nginx and PHP execution need the same behavior,
and blanket server-level CSP replacement would override app-owned policies. Static files and
responses generated before PHP executes are outside this delivery guarantee; their headers need
separate hosting configuration and verification. A header on one deployed URL does not establish
coverage of those other paths.

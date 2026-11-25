# Baseline security headers belong to the template

The template ships a small header baseline whose startup behavior is costly to repeat per app.
External form posts, framing, `<object>`/`<embed>`, or `<base>` require an app-owned override kept
consistent with `X-Frame-Options`.

`script-src` uses a per-request nonce and `'strict-dynamic'` so it works without per-app host
allowlists and matches the
[strict CSP AdSense supports](https://support.google.com/adsense/answer/16283098?hl=en-GB) (which
requires `'unsafe-eval'`). Other resource directives, reporting, `Permissions-Policy`, and HSTS
`includeSubDomains`/`preload` stay app-owned because they depend on each app's third-party hosts
and subdomain HTTPS coverage.

A [PHP header callback](https://www.php.net/manual/en/function.header-register-callback.php)
registered before maintenance and autoloading applies the same baseline to early errors and
maintenance responses; apps replace rather than append CSP because
[multiple enforced CSPs all apply](https://www.w3.org/TR/CSP3/#multiple-policies). Web-server-only
headers were rejected for PHP responses because local nginx would have to mirror them and a
server-level CSP would override app policies; the web server therefore covers static files only,
leaving pre-PHP responses outside this guarantee.

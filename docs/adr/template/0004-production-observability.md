# Production observability for XServer deployments

## Status

Accepted.

## Context

XServer supports synchronous PHP requests but no long-running queue workers or collectors.
Operators still need prompt notice of severe failures and a safe way to correlate user reports,
notifications, and application logs.

## Decision

Provide template-level observability that works entirely within the request lifecycle:

- Generate a request ID for every web request, ignoring untrusted incoming IDs. Add it and safe
  request/user metadata to log context and return it in `X-Request-Id`; never log request input,
  cookies, credentials, session payloads, or SQL bindings.
- Render dependency-free Blade `500` and `503` pages containing the request ID as an inquiry ID.
  For production-like `500` responses during an Inertia transition, use the Inertia server-error
  page so the application shell and navigation state remain intact. Do not perform that conversion
  while debugging; maintenance responses remain Blade-only.
- Notify Discord of 500-series and otherwise unhandled exceptions in `production` and `develop`
  when a webhook is configured. Include only concise diagnostic identity, request, user, and source
  metadata; redact database exception messages and omit sensitive request data.
- Rate-limit equivalent notifications while continuing to log every occurrence. Send the webhook
  after the response through the synchronous runtime's termination callback, with short timeouts
  and warning-only failure handling.

Discord notification of arbitrary critical log calls is outside scope. XServer API integration is
also outside scope and must not enter the error-response path.

## Considered alternatives

- **Use queues or a long-running collector.** Rejected because the production host cannot run
  either reliably.
- **Trust an incoming `X-Request-Id`.** Rejected because the shared-hosting edge is not a trusted
  issuer.
- **Use Inertia for every error response.** Rejected because the JavaScript or asset pipeline may
  itself be the cause of a failed full-page request.
- **Call the XServer API during exception handling.** Rejected because it adds a remote dependency
  and rate-limit surface to the failure path.

## Consequences and accepted risks

- Severe exceptions surface quickly without making Discord the primary log store.
- Warning-level events remain log-only and rely on request-ID correlation for investigation.
- Notification rate limiting may suppress repeated alerts, but the application log remains
  complete.
- Some diagnostic context is intentionally unavailable in Discord to protect secrets and personal
  data.

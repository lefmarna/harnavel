# Production observability for XServer deployments

## Status

Accepted.

## Context

The deployment target is XServer shared hosting. It supports a synchronous PHP request
model, but does not support long-running queue workers or SSR processes. Production
operators still need to notice severe application failures quickly and correlate a user
report with the application log entry that caused it.

XServer also provides a server API with access-log and error-log endpoints. Those endpoints
are useful for post-incident investigation, but they add a second remote dependency and rate
limit surface.

## Decision

Add template-level production observability with these boundaries:

- Assign every web request an application-generated `request_id`. Ignore incoming
  `X-Request-Id` values because the shared-hosting edge is not treated as a trusted request
  ID source.
- Share `request_id` plus safe request metadata with Laravel log context. Do not include
  request input, cookies, authorization headers, session payloads, or SQL bindings.
- Return `X-Request-Id` on web responses so operators can correlate browser reports,
  Discord notifications, and `storage/logs/laravel.log`.
- Render minimal Blade-only `500` and `503` pages that show the request ID as an inquiry ID.
  These pages must not depend on Inertia, Vite assets, or external resources.
- Notify Discord only for production HTTP exceptions that resolve to a 500-series status
  and for otherwise unhandled exceptions treated as HTTP 500.
- Keep Discord payloads short: request ID, environment, status, exception class, short
  exception message, method, URL without query string, route name, IP, user agent, user ID,
  and source file/line.
- Rate-limit Discord notifications with a lightweight cache key. The same exception class,
  file, line, route/path, and environment is notified at most once per five minutes. Laravel
  logging still records every occurrence.
- Send Discord notifications through `app()->terminating()` so the HTTP response is not
  delayed by the webhook call when the runtime supports termination callbacks. The webhook
  request uses short timeouts, and failures are swallowed after a warning log entry.
- Keep the feature disabled unless `ERROR_NOTIFIER_DISCORD_WEBHOOK_URL` is configured.

`Log::critical()` / `Log::emergency()` Discord notification is intentionally not part of the
initial implementation. It can be added later as a separate logging channel or Monolog
handler if operators need explicit non-exception alerts.

XServer API integration is also not part of the initial implementation. The future shape is
an operator command such as `php artisan xserver:logs --request-id=...` that retrieves
XServer access/error logs after a Discord notification has already identified the request
ID, URL, timestamp, and IP. XServer API calls must not run inside the production error
response path.

## Consequences

- Severe production exceptions surface quickly in Discord without turning Discord into the
  primary log store.
- Warning-level events remain in Laravel logs and are investigated through `request_id`
  correlation; they do not create Discord noise by default.
- A user-facing inquiry ID can be copied into support reports without exposing stack traces
  or input values.
- The design works on XServer shared hosting because it avoids queues and long-running
  collectors.
- Some context is intentionally omitted from notifications for privacy and secret-handling
  reasons. Deeper analysis relies on Laravel logs and, later, XServer API log retrieval.

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
- Share `request_id` plus safe request metadata with Laravel log context. Reset `user_id`
  to `null` at request start without resolving the guard, then share the actual `user_id`
  only after the authentication guard has resolved a user. Do not include request input,
  cookies, authorization headers, session payloads, or SQL bindings.
- Return `X-Request-Id` on web responses so operators can correlate browser reports,
  Discord notifications, and `storage/logs/laravel.log`.
- Render minimal Blade-only `500` and `503` pages that show the request ID as an inquiry ID.
  These pages must not depend on Inertia, Vite assets, or external resources.
- Notify Discord only for production HTTP exceptions that resolve to a 500-series status
  and for otherwise unhandled exceptions treated as HTTP 500.
- Keep Discord payloads short: request ID, environment, status, exception class, short
  exception message, method, URL without query string, route name, IP, user agent, user ID,
  and source file/line. Database exception messages are replaced with a fixed placeholder
  because PDO and query exceptions may include SQL binding values or duplicated key values.
- Rate-limit Discord notifications with a lightweight cache key. The same exception class,
  normalised message, route/path, and environment is notified at most once per five minutes.
  Laravel logging still records every occurrence.
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
- Because `resources/views/errors/500.blade.php` exists, Laravel renders it for every
  `HttpException` that resolves to 500 — including `abort(500)` — even when
  `APP_DEBUG=true`, so the framework debug error screen (whoops) is not shown for those.
  Uncaught non-HTTP exceptions still render the debug screen while debugging.

## Guidance for derived apps

Derived apps may override the 500 page (for example by converting it to an Inertia page).
When doing so:

- Keep showing the request ID as the inquiry ID so support reports stay correlatable with
  logs and Discord notifications.
- Keep `ErrorPageTest` green by adapting only its response assertions to the overridden
  rendering (for example asserting the Inertia component and its `requestId` prop instead
  of the Blade content). The "no stack traces or input values" expectations must stay.
- Note that Inertia responses embed the current URL (including the query string) in the
  page object, which is why the input-leak test sends input in the POST body rather than
  the query string.

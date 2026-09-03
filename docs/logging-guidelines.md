# Logging guidelines

Operational rules for application-level logging (`Log::info`, `Log::warning`, `Log::error`).

The only consumer is the developer, reading plain-text daily files with `grep`; XServer has no log
aggregation, so JSON would buy nothing. Web lines are found by `request_id`, console and scheduler
lines have none and are found by timestamp and exception class.

## Levels

`debug` is development-only, and `critical` / `alert` / `emergency` are left to the framework.
Unhandled exceptions already reach the log at `error` through Laravel's exception handler, so
application code does not log them again.

| Level     | When to use                                               |
| --------- | --------------------------------------------------------- |
| `error`   | A failure that affects the user and needs investigation.  |
| `warning` | Works for now, but becomes a problem if left unaddressed. |
| `info`    | A business event worth keeping for a later investigation. |

Three kinds of event qualify as that business event: authentication (login, logout, password reset
completion, 2FA enable and disable, Google account link and unlink), irreversible or high-impact data
changes (registration, account deletion, email address change), and the outcome of an external
service call. Routine CRUD and individual HTTP requests are not logged.

## Message conventions

Messages are English and name who or what plus what happened. Details go through the context array,
never string interpolation. A caught exception is summarized as `['exception_class' => $e::class]`
and never passed as the object: `QueryException` interpolates its bindings into the message, and a
catch-all `Throwable` cannot be checked in advance. No prefix is required; tracing is done through
`request_id`.

```php
// Good
Log::warning('Google OAuth database error.', ['exception_class' => $e::class]);

// Bad, the message carries the SQL bindings
Log::warning('Google OAuth database error.', ['exception' => $e]);

// Bad, interpolation instead of context
Log::warning("Google OAuth failed for user {$user->id}.");
```

## Sensitive data

Every web log line already carries `request_id`, `user_id`, `http_method`, `url`, `route`, `ip`, and
`user_agent` from `AssignRequestId` and `ShareAuthenticatedUserLogContext`. Never add passwords,
tokens, secrets, email addresses, phone numbers, names, `$request->all()` dumps, or query bindings.
Model IDs, status codes, and result classifications are safe.

## Discord notifications

Alerting is a separate system. `ProductionExceptionNotifier` reacts to unhandled 500s and builds its
payload from the request, not from anything logged, so no log call triggers a notification. The two
share only `request_id`, which is what makes a notification searchable here.

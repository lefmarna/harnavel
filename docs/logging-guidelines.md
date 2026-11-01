# Logging guidelines

Logs are plain-text daily files the developer reads with `grep`; XServer has no log aggregation, so
JSON would buy nothing. Web entries are correlated by `request_id`, console and scheduler entries by
timestamp and exception class.

## What to log

- `debug`: local development only.
- `info`: authentication events, high-impact account changes (registration, deletion, email change),
  and external service outcomes. Routine CRUD and ordinary requests are not logged.
- `warning`: rate-limit rejections, whose bursts are the visible trace of credential stuffing or mass
  registration.
- Unhandled exceptions already reach the log through Laravel's handler; application code does not
  log them again.

## Sensitive data

Messages are English; details go through the context array. Never log passwords, tokens, secrets,
email addresses, phone numbers, names, request dumps, or query bindings.

A caught exception is logged as `['exception_class' => $e::class]`, never as the object or its
message: `QueryException` puts its bindings in the message, and a mail transport failure quotes the
SMTP response, which names the recipient. A caught mail failure is therefore passed to
`ProductionExceptionNotifier` directly rather than to `report()`, which would write that response to
the log.

Unhandled exceptions keep their message, which diagnosis needs. Database connections mask bindings
and stack traces drop arguments, but what the database or SMTP server writes into its own error
stays, such as the duplicate value of a unique violation. Those lines stay in the server's log files.

## Discord notifications

`ProductionExceptionNotifier` reports unhandled 500s and rate-limit rejections, deduplicated per
route. It builds its payload from the request, not from the log, sharing only `request_id` with it,
and names the exception class but never its message, since Discord is a third party.

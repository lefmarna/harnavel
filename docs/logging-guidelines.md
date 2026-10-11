# Logging guidelines

The only consumer is the developer, reading plain-text daily files with `grep`; XServer has no log
aggregation, so JSON would buy nothing. Web lines are found by `request_id`, console and scheduler
lines by timestamp and exception class.

## What to log

`debug` is development-only. Unhandled exceptions already reach the log through Laravel's handler,
so application code does not log them again. `info` records authentication events (login, logout,
password reset completion, passkey registration and removal, Google link and unlink), high-impact
data changes (registration, account deletion, email address change), and the outcome of external
service calls. Routine CRUD and individual requests are not logged, except rate-limit rejections at
`warning`, because a burst of them is the visible trace of credential stuffing or mass registration.

## Sensitive data

Messages are English; details go through the context array. A caught exception is logged as
`['exception_class' => $e::class]`, never as the object or its message: `QueryException` puts its
bindings in the message, and a mail transport failure quotes the SMTP response, which names the
recipient (`550 5.1.1 <user@example.com> User unknown`). Never log passwords, tokens, secrets, email
addresses, phone numbers, names, request dumps, or query bindings.

Unhandled exceptions keep their message, which diagnosis needs. Database connections mask bindings
in exception messages and stack traces drop function arguments, but what the database or SMTP
server writes into its own error stays, such as the duplicate value of a unique violation. These
lines stay in the server's log files and are never forwarded.

## Discord notifications

`ProductionExceptionNotifier` reports unhandled 500s and rate-limit rejections, deduplicated per
route. It builds its payload from the request, not from the log, and shares only `request_id` with
it. The payload names the exception class but never its message, since Discord is a third party.

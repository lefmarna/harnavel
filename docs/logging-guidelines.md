# Logging guidelines

Operational rules for application-level logging (`Log::info`, `Log::warning`, `Log::error`).

## Audience

The sole consumer is the developer. Logs are read in two modes:

- **Reactive (web)** — Discord exception notification arrives → `grep` by `request_id` in `storage/logs/`.
- **Reactive (CLI)** — Discord notification for a console/scheduler exception has `request_id: unavailable`. Search by timestamp and exception class instead (e.g., `grep '2026-09-02 14:05' laravel-2026-09-02.log | grep RuntimeException`).
- **Proactive** — Occasional review of log files for unexpected warnings.

## Log levels

App code uses exactly three levels. Framework-level (`critical`, `alert`, `emergency`) and
development-only (`debug`) are not used in application code.

| Level     | When to use                                                  | Example                                                 |
| --------- | ------------------------------------------------------------ | ------------------------------------------------------- |
| `error`   | A failure that affects the user. Needs investigation.        | Payment processing failure, critical external API error |
| `warning` | Working now, but could become a problem if left unaddressed. | OAuth unexpected flow, notification delivery failure    |
| `info`    | Normal operation worth tracking for later investigation.     | User registration, email change, external API call      |

## What to log at `info` level

Only log business events in these categories:

- **Authentication events** — login success, logout, password reset completion, 2FA enable/disable, Google account link/unlink.
- **Important data changes** — user registration, account deletion, email address change, and other irreversible or high-impact operations.
- **External service calls** — outcome of calls to external APIs (enough detail to distinguish success from failure).

Do **not** log:

- Routine CRUD operations (list, show, ordinary update).
- Every HTTP request (that is the web server access log's job).

## Discord notifications

Discord notifications remain **exception-based only** (500-series unhandled exceptions via
`ProductionExceptionNotifier`). `Log::error` does not trigger Discord. If an event needs
immediate attention, throw an exception or implement a dedicated notifier.

## Message conventions

- Write messages in **English**.
- Include **who/what + what happened** (e.g., `User registered.`, `Google OAuth callback failed.`).
- Pass details via the **context array**, not string interpolation:

```php
// ✅ Good
Log::info('User registered.', ['user_id' => $user->id]);

// ❌ Bad
Log::info("User {$user->id} registered.");
```

- No mandatory prefix. Messages should be specific enough on their own. Tracing is done via `request_id` in shared context.

## Sensitive data

**Never include** in log context:

- Passwords, tokens, API keys, secrets.
- Email addresses, phone numbers, or other PII.
- Full request body dumps (`$request->all()`).
- Database query binding values.

**Safe to include**:

- `user_id`, `request_id` (already in shared context).
- Model IDs (`order_id`, `post_id`, etc.).
- Error messages (as long as they do not contain PII).
- Status codes and result classifications.

## Log format

Plain text (Monolog default formatter). JSON is unnecessary because XServer has no log
aggregation tooling — `grep` and visual inspection are the primary search methods.

## Retention and environment settings

| Setting          | Local (`.env.example`) | Production          |
| ---------------- | ---------------------- | ------------------- |
| `LOG_CHANNEL`    | `stack`                | `stack`             |
| `LOG_STACK`      | `daily`                | `daily`             |
| `LOG_LEVEL`      | `debug`                | `info`              |
| `LOG_DAILY_DAYS` | `14` (default)         | `45` (env override) |

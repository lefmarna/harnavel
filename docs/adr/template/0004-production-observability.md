# Production observability on XServer

Every web request receives a server-generated request ID for log and response correlation; incoming
IDs and sensitive request data are not trusted or recorded. Full-page 500 responses use
dependency-free Blade, while production-like Inertia transitions may use the Inertia error page.

Unhandled 500-series failures notify Discord when configured, after the response and within the
synchronous request lifecycle. Notifications are rate-limited, omit sensitive data and database
messages, and never make their own failure fatal. Queue workers, long-running collectors, and
XServer API calls are excluded because they are unavailable or unsafe in the error path.

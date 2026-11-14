# Hourly scheduler on XServer

Hourly invocation is the compromise between booting Laravel every minute for daily maintenance and
changing host settings whenever an app needs a different execution hour. Scheduled times are
therefore restricted to minute zero.

Missed cron invocations are unmonitored; tasks must catch up at their next scheduled run. Each
invocation must finish in a short, bounded time.

Tasks that need a minute other than zero, a sub-hourly interval, or a longer runtime get their own
cron entries outside the scheduler; the app records those entries in its `docs/infrastructure.md`,
since they exist only on the host.

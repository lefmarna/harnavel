# Hourly scheduler on XServer

Running `schedule:run` once per hour compromises between booting Laravel every minute for daily
maintenance and editing host cron settings whenever a task's hour changes. Scheduled times are
therefore restricted to minute zero.

Missed cron runs are unmonitored, so tasks must catch up on the next run and finish quickly. Tasks
requiring sub-hourly intervals or long runtimes use dedicated cron entries documented in the app's
`docs/infrastructure.md`.

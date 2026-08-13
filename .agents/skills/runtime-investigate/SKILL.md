---
name: runtime-investigate
description: Investigate a deployed environment on XServer — which release is live, HTTP health, web access and error logs, and Cron entries. Use only when diagnosing something already deployed; local development and local test failures are out of scope. Application logs are out of reach and every operation is read-only.
---

# Runtime Investigate

Answer questions about what a deployed environment is doing right now, using the smallest amount of
remote data that settles the question.

## Rules

- `pnpm runtime-ops <command>` is the only way in. Never invoke `xserver`, `ssh`, `curl`, or the
  XServer API directly. Environments are derived from `.htaccess` and `.htaccess.develop` as of the
  commit whose files are on the server, which is not always the last successful one: releases are
  applied in place, so a run that failed after its transfer still left its own files behind. `env`
  prints what was derived and which commit it came from, so do not parse those files yourself or
  read them from the working tree. That commit is inferred from the delivery runs GitHub still
  holds; deleting a run hides the release it placed, so treat the commit as strong evidence rather
  than proof and say so if a conclusion rests on it.
- A `WARNING:` line above any result means the release on the server is unfinished or older than the
  newest run. Say so when reporting, because the code being investigated may not be the code that
  was meant to be live.
- Everything here is read-only. There is no command that changes a server, and the XServer API key
  is expected to be a read-only key. If a task needs a write, report the exact change for a human to
  apply rather than looking for another route.
- Never assume which environment is meant. `main` and `develop` map to their own environments; any
  other branch resolves nothing and needs an explicit `--env`. Say which environment you used.
- Ask for the narrowest window first. Raise `--lines` only after a smaller window proved
  insufficient, and prefer `--keyword` over more lines. The cap is 200.
- XServer files a subdomain's web logs under its parent domain, so one log holds several sites.
  `errors` and `access` attribute every line to a site before showing it — by the access log's vhost
  field, and by the deployment path in the error log — and the first line of the output states how
  many lines were inspected, shown, attributed elsewhere, or attributed to nothing. Read that header
  before trusting a count: a line naming no site is never shown as this environment's.
- Quote only the lines that carry the finding. Do not paste whole command outputs forward.

## Order

Stop as soon as the question is answered.

1. **`pnpm runtime-ops status [--env NAME]`** — the environment mapping, the last delivery run, and
   a live `/up` check. Start here for anything about deployment or "is it up".
   `delivery.yml` already verifies the remote toolchain, migrations, and `/up` inside the run that
   deployed, so a successful run is evidence; do not re-derive it from the server.
2. **`pnpm runtime-ops errors [--env NAME] [--lines N] [--keyword TEXT]`** — the XServer web error
   log, for failures the application never saw: 500s with no Laravel entry, TLS, rewrite, or
   permission problems.
3. **`pnpm runtime-ops access [--env NAME] [--lines N] [--keyword TEXT]`** — the XServer access log,
   for traffic-shaped questions: whether a request arrived, its status, its frequency.
4. **`pnpm runtime-ops cron`** — the Cron entries registered on the server. Server-wide, not
   per-environment. What a scheduled task *does* is in `routes/console.php`, not here.

## Application-level failures

There is no command that reads `storage/logs/laravel.log`; reaching it would mean giving this
machine an SSH key or exposing a log route in production, and neither is worth it. For a 5xx the
Discord notification is the application-level evidence: it carries the request ID, environment,
exception class and message, HTTP method, URL, route, and `file:line`. Ask the user for that
notification, or for the `X-Request-Id` of the failing response, and correlate it with the code.
Use `errors` to confirm whether the web tier saw the failure at all.

## Done when

The finding names the environment, the evidence it rests on, and whether that evidence came from
GitHub, HTTP, the XServer log, or the failure notification. If the cause is in application code, hand off to
normal development — fixing it is not part of this skill.

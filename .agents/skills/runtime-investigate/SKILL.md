---
name: runtime-investigate
description: Investigate a deployed environment on XServer — which release is live, HTTP health, web access and error logs, and Cron entries. Use only when diagnosing something already deployed; local development and local test failures are out of scope. Application logs are out of reach and every operation is read-only.
---

# Runtime Investigate

Answer questions about what a deployed environment is doing right now, using the smallest amount of
remote data that settles the question. There is no wrapper: run the commands below directly, and
state which environment every finding came from.

## What you may run

The XServer API key is read-only and restricted to this account's server, so reading cannot break
anything, and nothing here changes a server. If a task needs a change, report the exact command for
a human to run rather than looking for another route.

`xserver` is installed as a host tool. If the command is not found it is on mise's shim path, which
non-interactive shells do not inherit — use `mise exec -- xserver …` instead.

## Resolve the environment first

`main` deploys production and `develop` deploys develop; any other branch belongs to neither, so ask
which is meant rather than guessing.

- **The live commit** is the one placed by the last delivery run that finished its transfer, which
  is not always the newest run:

  ```bash
  gh run list --workflow delivery.yml --branch <branch> --limit 1000 \
    --json attempt,conclusion,createdAt,databaseId,headSha,status,updatedAt,url
  ```

  `--limit` pages internally, but a branch-filtered listing is capped at 1000 rows, so 1000 is a
  ceiling to respect rather than a number to raise. Fewer rows than the limit is what says every
  retained run was seen; exactly 1000 means the listing was cut short, so narrow the window with
  `--created` and repeat, or report the commit as undetermined. Deleted runs leave no row at all,
  which is the residual reason this can only ever be evidence.

  The unit is the attempt, not the run. Re-running a run adds an attempt rather than creating a new
  run, so attempts interleave across runs: an old run re-run after a newer one deployed carries the
  newest `updatedAt`, yet it was an earlier attempt of it that placed files, before the newer run
  did. The listing and `gh run view` show only the latest attempt; the listing's `attempt` says how
  many exist, and `gh run view <id> --attempt <n> --json jobs` reads a specific one.

  Files are placed by a `Deploy to XServer via Rsync` step that succeeded, and its `completedAt` is
  when. The live commit is therefore the `headSha` of the attempt whose rsync completed last — not
  the newest run, and not the first success found working backwards. Releases are applied in place,
  so a step that failed may still have replaced part of the tree, while a skipped one means an
  earlier step failed and that attempt left the files alone.

  Walk runs by `updatedAt` descending, expanding every attempt. A run's `updatedAt` is never earlier
  than its own steps, so once the best `completedAt` beats every unexamined run's `updatedAt`,
  nothing left can displace it and the scan is done. If nothing settles it — an interrupted
  transfer, a capped listing, a deleted run — say the live commit is undetermined rather than
  picking one.

- **Hosts** come from that commit, not from the working tree, which may be a feature branch or an
  unreleased edit: `git show <sha>:.htaccess` allows `!^(?:(?:www|…)\.)?<apex>$`, whose apex is the
  production host, and `git show <sha>:.htaccess.develop` allows the single host that is develop's.
- **Basic auth**: if that revision's `.htaccess.develop` contains `require valid-user`, the
  environment is behind basic auth, so `/up` and every other request to it need `curl --user`.

## Order

Stop as soon as the question is answered.

1. **Deployment state** — the delivery run above, and a health check that cannot hang on a host that
   has stopped answering:

   ```bash
   curl -fsS -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 10 https://<host>/up
   ```

   `delivery.yml` already verifies the remote toolchain, migrations, and `/up` inside the run that
   deployed, so a successful run is evidence; do not re-derive it from the server.
2. **XServer web error log** — failures the application never saw: 500s with no Laravel entry, TLS,
   rewrite, or permission problems.

   ```bash
   xserver --format json server log error --domain <apex> --lines 50
   ```

3. **XServer access log** — traffic-shaped questions: whether a request arrived, its status, its
   frequency. Same options, with `log access`.
4. **Cron** — `xserver --format json server cron list`. Server-wide rather than per-environment, so
   it lists every site on the account. What a scheduled task *does* is in `routes/console.php`.

Both log commands answer `{"domain": …, "log": "…"}`, where `log` holds the raw lines in one string.
`cron list` answers `{"crons": [...]}`.

## Reading a shared log

**XServer files a subdomain's web logs under its parent domain.** Always pass the apex as
`--domain`, including when investigating develop, then work out which lines are that environment's:

- **Access log**: the first field of each line is the host that served it. That field, and only that
  field, decides which environment a line belongs to.
- **Error log**: there is no host field. The deployment path names the site —
  `/home/<account>/<apex>/<site>/`, where `<site>` is `public_html` for the apex and the hostname
  for a subdomain. A sibling directory such as `cache` or `htpasswd` belongs to no environment.
- A hostname anywhere else in a line — a referer, a request URI, a value echoed by PHP — says
  nothing about who served it. Never attribute a line on that basis.
- `--keyword` is a substring match over the whole line. It can narrow to a subdomain but never to
  the apex, because every `dev.example.com` line also contains `example.com`.

## Volume

Start at `--lines 50` and raise it only after a smaller window proved insufficient; prefer
`--keyword` over more lines. Quote only the lines that carry the finding, never a whole log.

## Application-level failures

There is no way to read `storage/logs/laravel.log` from here: it would need an SSH key on this
machine or a log-reading route in production, and neither is worth it. For a 5xx the Discord
notification is the application-level evidence — it carries the request ID, environment, exception
class, HTTP details, and `file:line`, but never the exception message. Ask the user for that notification, or for the
`X-Request-Id` of the failing response, and correlate it with the code.

## Done when

The finding names the environment, the release it was checked against, and where the evidence came
from. If the cause is in application code, hand off to normal development.

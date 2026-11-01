---
name: runtime-investigate
description: Investigate a deployed environment on XServer — which release is live, HTTP health, web access and error logs, and Cron entries. Use only when diagnosing something already deployed; local development and local test failures are out of scope. Application logs are out of reach and every operation is read-only.
---

# Runtime Investigate

Answer what a deployed environment is doing now with the smallest remote query that settles the
question, and name the environment behind every finding. The XServer API key is read-only and
limited to this account's server; when a task needs a change, report the exact command for a human
to run instead of looking for another route. If `xserver` is not found, use
`mise exec -- xserver …`, because non-interactive shells lack mise's shim path.

## 1. Resolve the environment and live commit

`main` deploys production and `develop` deploys develop; for any other branch, ask which is meant.

- **Live commit**: the `headSha` of the delivery attempt whose `Deploy to XServer via Rsync` step
  succeeded with the latest `completedAt` — not the newest run, and not the first success found
  working backwards.

  ```bash
  gh run list --workflow delivery.yml --branch <branch> --limit 1000 \
    --json attempt,conclusion,createdAt,databaseId,headSha,status,updatedAt,url
  ```

  - A branch-filtered listing caps at 1000 rows: exactly 1000 means it was cut short, so narrow it
    with `--created` or report the commit as undetermined. Deleted runs leave no row, so the result
    is evidence, not proof.
  - Re-running adds an attempt to the same run, and the listing and `gh run view` show only the
    latest, so read every attempt with `gh run view <id> --attempt <n> --json jobs`. An old run
    re-run after a newer deploy carries the newest `updatedAt` although an earlier attempt placed
    its files.
  - Walk runs by `updatedAt` descending; stop once the best `completedAt` beats every unexamined
    run's `updatedAt`.
  - Releases apply in place, so a failed rsync step may have replaced part of the tree, while a
    skipped one left the files alone. When nothing settles it, report the live commit as
    undetermined.
- **Hosts** come from the live commit, not the working tree: `git show <sha>:.htaccess` allows the
  production apex, and `git show <sha>:.htaccess.develop` the single develop host. If that
  `.htaccess.develop` has `require valid-user`, every request to develop, `/up` included, needs
  `curl --user`.

## 2. Query in order, stopping once answered

1. **Deployment state**: the delivery run above, which already verified the remote toolchain,
   migrations, and `/up`, plus a health check that cannot hang:

   ```bash
   curl -fsS -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 10 https://<host>/up
   ```

2. **XServer web error log**, for failures the application never saw (500s without a Laravel entry,
   TLS, rewrite, or permission problems). It answers `{"domain": …, "log": "…"}`:

   ```bash
   xserver --format json server log error --domain <apex> --lines 50
   ```

3. **XServer access log**, for whether a request arrived, its status, and frequency: the same with
   `log access`.
4. **Cron**: `xserver --format json server cron list`, answering `{"crons": [...]}` for every site on
   the account.

## 3. Attribute and limit log lines

- XServer files a subdomain's web logs under its parent, so always pass the apex to `--domain`.
- **Access log**: only the first field, the serving host, assigns a line to an environment.
- **Error log**: the path `/home/<account>/<apex>/<site>/` names the site (`public_html` for the
  apex, the hostname for a subdomain); siblings such as `cache` or `htpasswd` belong to none.
- A hostname elsewhere in a line (referer, URI, PHP output) never attributes it. `--keyword` matches
  the whole line, so it can narrow to a subdomain but never to the apex.
- Start at `--lines 50`, prefer `--keyword` over more lines, and quote only the lines carrying the
  finding.
- `storage/logs/laravel.log` is unreachable from here. For a 5xx, ask the user for the Discord
  notification (request ID, environment, exception class, HTTP details, `file:line`, never the
  message) or the response's `X-Request-Id`, and correlate it with the code.

Done when the finding names the environment, the release it was checked against, and its evidence
source. A cause in application code goes back to normal development.

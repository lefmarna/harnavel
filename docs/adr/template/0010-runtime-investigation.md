# Runtime investigation from local agents

Investigating a deployed environment is a skill, not a program. One explicitly invoked skill carries
what an agent cannot work out on its own — the XServer CLI's read commands, where an environment's
host is declared, and how a shared log is read — and the agent runs those commands itself. Nothing
about XServer is present in an ordinary session, because the skill is loaded only when invoked.

The read-only API key is the safety boundary. It is issued for this account's server, covers the
`server` service alone, and permits no writes, so a mistaken command fails at the server rather than
being caught on the way there. A wrapper exposing a narrowed set of operations was built and then
removed: with the key correct it defended against nothing the server does not already refuse, while
restating in code what a sentence in the skill conveys. An XServer MCP server is rejected for the
same reason, plus a permanent tool surface spanning domain, DNS, database, mail, and SSH-key
mutation bought for four reads.

Responsibilities stay split by what each source knows. Deployment facts come from GitHub Actions,
because `delivery.yml` proves the remote toolchain, migrations, and `/up` inside the run that
deployed; reachability comes from HTTPS `/up`; server-level facts come from the XServer CLI. Which
commit is live follows from the last delivery run, read with the knowledge that releases are applied
in place: a run that failed after its transfer already replaced the files, and one that failed
before it did not. Recording the commit in the release itself, so the site could report it without
that reasoning, was built and then removed — it put a step that can fail a deployment into the
release path to spare an occasional diagnostic one inference, and an agent that can read a failed
run can say the commit is undetermined instead.

Local agents never hold an SSH key, so `storage/logs/laravel.log` is out of reach. A workflow that
reused the deployment credentials to grep it for one request ID, and a token-authenticated route
inside the application, were both rejected as more machinery than the evidence is worth: the Discord
notification for a 5xx already carries the request ID, environment, exception, HTTP details, and
`file:line`. Write access is out of scope entirely, and Cron additions stay manual, which Laravel's
scheduler keeps rare by routing new work through `routes/console.php` behind one entry.

One thing an agent may not guess is which environment is meant: `main` and `develop` deploy their
own, and any other branch deploys neither, so an unrecognised branch is a question rather than a
default.

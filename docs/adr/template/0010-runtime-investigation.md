# Runtime investigation from local agents

Investigating a deployed environment goes through one explicitly invoked skill and one wrapper,
`scripts/runtime-ops.ts`, exposing exactly five read-only operations. Nothing about XServer is
present in an ordinary session: the wrapper is a package script like any other, and it reads
nothing until it runs. An XServer MCP server is rejected
because its published surface spans domain, DNS, database, mail, and SSH-key mutation, which is a
large permanent tool surface bought for four reads; the official CLI reaches the same four reads
through the shell that already exists. Teaching agents the CLI directly is also rejected: that same
surface includes `server domain delete --delete-files`, and an unbounded `--lines` empties a log
into the context window.

Environments are derived from `.htaccess` and `.htaccess.develop` — the served hostnames, and
whether basic auth guards them — rather than restated in a manifest, so the mapping cannot drift
from the rules the server actually enforces and no new file needs an ownership rule at template
merge time. The branch pair is an invariant of `delivery.yml`, which deploys only `main` and
`develop`. A parse that does not find a host condition fails instead of guessing.

An environment's web-log domain is tracked separately from the host it serves, because
XServer files a subdomain's access and error logs under its parent domain: one log carries several
environments, and a develop query answered from the parent log would otherwise return production
traffic. Attribution is decided positively, on the field that actually names the site: the access log's
leading vhost field, and, in the error log, the directory following the apex in the deployment path
`/home/<account>/<apex>/<site>/`, which is `public_html` for the apex itself and the hostname for
every subdomain. Only those two shapes name a site: a sibling directory such as `cache` or
`htpasswd` belongs to no environment. The path is read only from the server-generated part of an
Apache message — the line must carry Apache's bracketed prefix, the referer Apache appends is cut
away with everything after it, quoted fields are dropped, and a request line's target is skipped —
because a referer or request URI is client-supplied and can spell out any path it likes, including a
relative one that looks exactly like a deployment path. Even then the message body can quote a path it was
given, so paths are taken only from the message forms the server resolves itself, every occurrence
is collected rather than the first one trusted, and a message naming more than one site is left
unattributed. An unrecognised form yields nothing and is counted as unattributed, which surfaces a
gap in that list instead of hiding it behind a guess. Subtracting other
environments is rejected, because it credits whatever is left over — another subdomain's traffic, or
a line naming no site at all — to the environment that happened to be asked. Testing the whole line
for a hostname is rejected too, in both directions: a production request whose referer points at the
develop host would otherwise be handed to develop and removed from production. Lines that name no
site are counted and withheld rather than shown as this environment's. When a log is shared the
server-side window widens to the hard cap before attribution, since trimming to the requested line
count first can return nothing while the environment's own lines sit just outside it. Size is
bounded once, on what is displayed: capping the payload on the way in would decide which lines exist
before anything knows whose they are, drop a whole environment whose lines happen to sit outside the
retained bytes, and feed its own truncation notice back in as another line to attribute. That bound
spends its budget on whole lines and reports the count that survives it, so the header never claims
more lines than the body holds and no line arrives with its beginning missing. A line too large for
the budget on its own is shown as a truncated tail, cut on a character boundary and reported as that
rather than counted among the lines withheld.

An environment's settings are read from the commit whose files are actually on the server, never
from the working tree or a branch tip: the working tree describes whatever is checked out,
`origin/<branch>` may be unfetched or carry commits whose deploy failed, and a local branch may hold
commits that were never pushed. Because releases are applied in place, the newest successful run is
not that commit either — rsync replaces the live directory before Composer, migrations, `artisan
up`, and the health check, so a run that failed afterwards still left its own `.htaccess` behind.
The live commit is therefore the most recently updated run that finished its transfer. Runs are
walked by last update rather than creation, because re-running an older run adds an attempt to it
instead of creating a new one, so its files can be the ones on disk even though another run was
created later. A run counts as having transferred when it succeeded or when its rsync step did, and
that result carries a warning whenever the release is unfinished or older than the newest run. The scan is trusted only when it read the whole
history. Nothing weaker is sound: runs are listed newest-created first while the live release is the
one updated last, so a run past the listing limit can hold the files on disk however old its
creation date is, and an age cut-off would bound only which runs may still be re-run in future, not
which past re-run placed what is live. A repository whose history outgrows one listing is reported
as inconclusive rather than answered from the visible part.

That listing bounds what can be read, not what is true: completed runs can be deleted, and a deleted
run leaves nothing behind, so the release it placed cannot be seen at all. No source reachable from
here closes that gap — the deployment records GitHub derives from `environment:` are not verifiably
immune to it either — and the one authoritative answer, a released commit recorded on the server
itself, needs a read path this design does not have: an SSH key on the developer's machine, which is
rejected outright, or the commit published over HTTP from a private repository. The resolved commit
is therefore reported as inferred from delivery runs rather than asserted, so a conclusion that
rests on it can be weighed for what it is. Exhausting it without finding a transfer is likewise reported rather than treated as
an empty server. The moment a release was placed is read from the transfer step's own completion
time, since the run keeps running through Composer, migrations, and the health check afterwards. Only
an explicit `skipped` — on the deploy job, or on the step once an earlier one failed — proves the
files were left alone; absence of a job or a step proves nothing and is reported as unknown, as are
a failed, cancelled, or still-running transfer, since an interrupted copy leaves no commit that can
be called live. Every command that resolves an environment carries the resulting warning, not just
the ones that describe it.
An absent commit or an environment nothing has deployed to fails the same way rather than falling
back. Environment names are matched against own
properties only, so an inherited name such as `constructor` cannot resolve to an environment that
does not exist.

Responsibilities stay split by what each source actually knows. Deployment facts come from GitHub
Actions, because `delivery.yml` already proves the remote toolchain, migrations, and `/up` inside
the run that deployed; reachability comes from HTTPS `/up`; server-level facts come from the XServer
CLI. A server-side release marker recording the commit on the
server is the one thing that would establish which release is live outright, and it is not adopted
only because reading it back needs a path this design does not have: an SSH key on the developer's
machine, which is rejected outright, or the commit published over HTTP from a private repository.
The run history is used in its place and reported as the inference it is.

Local agents never hold an SSH key, and the application log is out of reach as a result: reading
`storage/logs/laravel.log` would require either an SSH key on the developer's machine or a
log-reading route and another production secret in every deployed app, and both cost more than they
return. A workflow that reuses the deployment credentials to grep the log for one request ID was
built and then removed as unearned complexity — dispatching a run, correlating it back, and
returning an artifact, all to reach evidence the failure notification already carries. For a 5xx the
Discord notification is the application-level record: request ID, environment, exception class and
message, HTTP details, and `file:line`. Write access is out of scope entirely — the API key is
expected to be read-only, so a wrapper bypass still fails at the server — and Cron additions stay
manual, which Laravel's scheduler keeps rare by routing new work through `routes/console.php`
behind one entry.

The environment is never inferred beyond what is declared. `main` and `develop` resolve to their own
environments, any other branch resolves to nothing, and an undetectable branch is an error rather
than a default, so no unregistered branch can be treated as production.

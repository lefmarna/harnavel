# Shared development infrastructure with isolated Worktree state

Local development usually uses one Worktree, occasionally two or three, and at most
five. A dedicated database process for every development, PHPUnit, and E2E database
adds memory and disk overhead even when no tests are running.

Each application shares one MariaDB 10.11 service, one Mailpit service, and a Composer
download cache across its Worktrees. Each active Worktree runs its own PHP and Nginx
containers and host Vite process. Each Worktree tags its own PHP image, but an
identical Dockerfile resolves to the same layers through Docker's build cache
instead of forcing a complete rebuild.

Development, PHPUnit, and E2E use separate databases and separate restricted accounts
per Worktree. The PHPUnit and E2E names derive from the development database and
account by suffix and share its password: the accounts separate privileges, not
secrets, because all three live in the same `.env`. No application or test process
uses the database administrator account, which keeps the legacy fixed local password
behind the loopback-only port.
Database names and service addresses are explicit inputs to E2E's destructive-target
guard. CI retains its existing disposable database services and configuration.

The primary checkout owns the persistent database directory, and every Worktree
operates the shared services through the primary checkout's Compose definition. Moving from the legacy
stack stops its database before the shared service mounts the same directory. Neither
normal Worktree shutdown nor image cleanup removes the shared database or cache.
Deleting an additional Worktree removes only its allocated databases and accounts;
the primary database cannot be deleted by that command.

The Git common directory stores the application-wide allocation registry and lock.
An application reserves 100 ports; Worktrees reserve ten-port slots. Setup records
the Compose project, ports, and credentials in each Worktree's private `.env` before
starting services. Symlinks to mutable dependency trees or state are rejected.

Orca runs repository-defined setup and archive commands, waits for setup before
launching an agent, and opens a separate Vite terminal. Browser profiles must still
be isolated when multiple Worktrees use the same hostname: ports do not isolate
cookies, including Laravel's fixed-name XSRF cookie.
Default tabs start before the setup terminal in Orca 1.4.211. The Vite command
therefore waits for a Worktree-local success marker before starting its server.

The tradeoff is a shared failure domain: database restarts affect all Worktrees of
that application, and Mailpit's inbox mixes their mail. Shared infrastructure is
stopped explicitly only after every attached Worktree has shut down.

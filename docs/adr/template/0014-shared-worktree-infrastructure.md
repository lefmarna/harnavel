# Shared development infrastructure with isolated Worktree state

Local development usually runs one Worktree and at most five at once. A database
process per development, PHPUnit, and E2E database costs memory even when idle.

Each application shares one MariaDB, one Mailpit, and a Composer cache across its
Worktrees; the primary checkout owns the data directory. Each Worktree runs only PHP
and Nginx and gets separate development, PHPUnit, and E2E databases with restricted
accounts, so tests cannot touch the development database. No application or test
process uses the administrator account.

The tradeoff is a shared failure domain: a database restart affects every Worktree of
the application, Mailpit mixes their mail, and browsers need separate profiles
because ports do not isolate cookies.

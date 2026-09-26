# Worktree development

The local runtime shares one MariaDB and one Mailpit per application. Each active
Worktree adds only PHP and Nginx containers; Vite runs on the host. PHPUnit and E2E
databases are schemas in the shared server, not additional persistent processes.
One Worktree uses four persistent containers; three use eight and five use twelve.
The E2E web container runs only for the duration of a test run. Mailpit's inbox is
shared, so messages from different Worktrees appear together. A restart of the
shared database affects every active Worktree of that application.

## Initial setup

Use `mise run setup` for a fresh checkout. Set `DEV_PORT_BASE` in the primary `.env`
before first setup if the default application block is already in use.

For an existing six-container checkout, run `mise run dev:migrate` in the primary
checkout first. It saves `.env.worktree-backup`, shuts down the legacy stack, and
mounts the existing `storage/mariadb` in the shared service. It preserves the
primary development database, application key, and local secrets. It does not copy
live database files or mount them in two active MariaDB instances.

`compose.legacy.yaml` is retained for migration and rollback. To roll back, stop all
new Worktrees and `mise run infra:stop`, restore `.env.worktree-backup` to `.env`,
then use `docker compose -f compose.legacy.yaml up -d`. Additional Worktree databases
remain in the data directory; rollback does not delete them. Keep the MariaDB major
version at 10.11 when reusing this directory.

## Ports and state

| Offset | Service        | Lifetime                        |
| ------ | -------------- | ------------------------------- |
| 0      | Web            | Per Worktree                    |
| 1      | Vite           | Per Worktree                    |
| 2      | MariaDB        | Shared, primary slot            |
| 3      | Mailpit SMTP   | Shared, primary slot            |
| 4      | Mailpit UI     | Shared, primary slot            |
| 5      | E2E web server | Per Worktree, only during tests |

The primary checkout uses slot zero. Additional Worktrees automatically reserve
slots one through nine under a lock in the common Git directory. A slot adds ten to
the application base port. Assign distinct 100-port blocks to different applications.
Allocations persist through stop/start. Choose the base before initial setup; do not
change it while registered Worktrees exist.

Run `mise run dev:info` for URLs, database names, and Compose identifiers. Run normal
commands from that Worktree's root, including MCP, Artisan, Wayfinder, and tests.
The `.env` file is authoritative; do not export conflicting Compose or port values
in a global shell profile. Setup writes only keys listed in `.env.example`. It
updates existing lines in place and inserts a missing key, with its preceding
comments, at the matching `.env.example` position.

Each Worktree owns its `.env`, `node_modules`, `vendor`, Laravel caches, build output,
`public/hot`, uploads, and test reports. Setup copies local configuration from the
primary checkout and rewrites the infrastructure identifiers and credentials. It
preserves the application key when one already exists. Downloads use the host pnpm
store and the shared Composer cache; mutable installed dependencies are not linked.
An additional Worktree's `.git` file points to the primary checkout's Git directory
by absolute host path, so `DEV_GIT_COMMON_DIR` mounts that directory at the same
path in the PHP container. Git-based tools such as `pint --dirty` then work there too.

Database accounts have privileges only on their individual allocated schemas. The
PHPUnit account may also create its own parallel worker schemas
(`<test database>_test_<n>`). `compose.yaml` derives the PHPUnit and E2E databases
and accounts from `DB_DATABASE` and `DB_USERNAME` with `_testing`/`_test` and
`_e2e` suffixes; they share `DB_PASSWORD`. The test runner and E2E server select
their dedicated accounts and ignore a cached development configuration or a
conflicting `DB_URL`. Setup refuses a non-empty `DB_URL` in `.env` because Laravel
would prefer it over the Worktree's development connection. Run E2E serially within
a Worktree. Different Worktrees have distinct ports and databases; E2E uses the
`NGINX_PORT + 5` port of the slot.

## Orca

The checked-in `orca.yaml` defines:

- Setup: `mise run setup`.
- Agent startup: wait for setup.
- Archive/removal: `mise run worktree:remove`.

In Repository settings, select the repository/shared command source. A pre-existing
local-only command such as `pnpm install` overrides the repository setup definition.
Do not add `.env`, `storage`, `vendor`, `node_modules`, Laravel caches, or build output
to Worktree Shared Paths or `worktree.sharedDirectories`. Orca opens no Vite tab:
setup and `dev:migrate` run `mise run assets`, which drops a `public/hot` left by a
stopped Vite and builds `public/build`. `pnpm dev` runs only while frontend changes
need a live server, so idle Worktrees stay light. When setup finds an
empty database, it records `storage/framework/.dev-seed-pending` before migrating and
removes it only after seeding, so a rerun retries an interrupted initial seed.
Use a separate Orca browser profile for each Worktree, or separate browser hostnames;
changing only ports does not isolate the XSRF cookie.

Merge/commit the tooling before creating a Worktree from that revision: Orca reads
`orca.yaml` from the new checkout, so an uncommitted primary-checkout file is absent
there. The same applies to the scripts and mise tasks used by its hooks.

The archive command stops this additional Worktree and deletes its three databases,
their parallel test schemas, accounts, and port reservation. It refuses to delete
primary-checkout data. Run it before plain `git worktree remove` too; deleting a
directory without running the hook leaves its database and reservation behind. A
failed archive hook must be resolved before deleting the Worktree. An archived
Worktree can be initialized again if its files are retained.
Stop the Worktree's Vite server, if running, before running manual cleanup.

`mise run down` and `mise run destroy` remove only this Worktree's containers. They
preserve the shared database, caches, and image. After all Worktrees are down,
`mise run infra:stop` stops the shared services; it refuses while any attached
Worktree or E2E server remains running.

## Applying the change to derived applications

These are proposed blocks, not changes already applied to the applications:

| Application               | `DEV_PORT_BASE` |
| ------------------------- | --------------- |
| laravel-app-template      | 20000           |
| app-one                | 20100           |
| app-four | 20200           |
| app-two                 | 20300           |
| app-three             | 20400           |

Merge the shared Compose files, local environment scripts, test runners, Playwright
configuration, and mise tasks from the template, preserving application-specific
tasks and E2E setup. Set the application's base port in both `.env.example` and the
primary local `.env`, then run `mise run dev:migrate` from its primary checkout.

Apply these files together so the setup and cleanup hooks use the same lifecycle:

- `compose.yaml`, `compose.infrastructure.yaml`, and `compose.legacy.yaml`.
- `scripts/dev-environment.ts`, `scripts/local-env.ts`,
  `scripts/run-pest-locked.sh`, and `scripts/run-e2e-artisan.sh` (retain executable
  permission on the shell scripts).
- `mise.toml` setup/runtime tasks and `orca.yaml`.
- `.env.example` infrastructure/port defaults and the backup/ready-marker entries
  in `.gitignore`.
- `playwright.config.ts`, `e2e/e2e-database-target.ts`, `e2e/global-setup.ts`, and
  `e2e/support/artisan.ts`.
- README/AGENTS operational guidance and this document/decision record.

Save each application's current Compose file as its migration/rollback definition;
do not overwrite it with the template's legacy file if its services differ.

| Application               | Application-specific merge points                                                                                                                                             |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| App One                | Keep existing app tasks, fixtures, and settings; replace the local-only `pnpm install --frozen-lockfile` Orca setup with repository hooks.                                    |
| App Four | Keep existing app tasks, fixtures, and settings; there is no existing local Orca setup command to replace.                                                                    |
| App Two                 | Keep existing app tasks, fixtures, and settings; replace the local-only `pnpm install --frozen-lockfile` Orca setup with repository hooks.                                    |
| App Three           | Keep `e2e/app-global-setup.ts` as Playwright's setup entry, `.e2e-accounts` per Worktree, `ja-JP` locale, and loopback Vite readiness URL; replace the local-only Orca setup. |

Keep `APP_NAME`, the primary `DB_DATABASE`, presentation, keys, and secrets app-owned.
Remove any application-specific hardcoded E2E port calculation in favor of `NGINX_PORT + 5`.
Preserve App Three's extra account-fixture setup. Update Orca's local-only setup
settings for App One, App Two, and App Three to use the repository hooks.
For an old Worktree created before the merge, bring in the tooling changes before
running setup; otherwise it can still start the legacy stack.

No derived application is modified by the template's setup or migration commands.
After each application's migration, verify its existing data, two simultaneous
Worktree URLs, independent test databases, and cleanup that leaves the primary
checkout running. Use that application's existing backend and E2E suites to verify
its fixtures and custom setup.

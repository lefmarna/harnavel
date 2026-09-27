# Worktree development

Each application shares one MariaDB and one Mailpit across its Worktrees. Each
Worktree adds only PHP and Nginx containers and gets its own development, PHPUnit,
and E2E databases. Mailpit's inbox is shared.

## Setup

Run `mise run setup`. It starts the shared services and this Worktree, migrates,
seeds an empty database, and builds assets; rerunning retries an interrupted seed.
`mise run dev:info` shows this Worktree's URLs and names.

`DEV_PORT_BASE` in `.env.example` reserves a 100-port block per application: 20000
template, 20100 app-one, 20200 app-four, 20300 app-two, 20400
app-three. The primary checkout uses slot 0; additional Worktrees get slots 1-9.

| Offset | Service        | Scope                  |
| ------ | -------------- | ---------------------- |
| 0      | Web            | Per slot               |
| 1      | Vite           | Per slot               |
| 2      | MariaDB        | Shared                 |
| 3      | Mailpit SMTP   | Shared                 |
| 4      | Mailpit UI     | Shared                 |
| 5      | E2E web server | Per slot, during tests |
| 9      | Setup lock     | Shared, while updating |

Setup writes only keys listed in `.env.example`, at the same position. Keep `DB_URL`
empty; it would override the Worktree's database. Do not share `.env`, `storage`,
`vendor`, `node_modules`, caches, or build output between Worktrees.

Vite is not started automatically. Run `pnpm dev` for frontend work; otherwise pages
use the assets built by setup.

## Removal

`mise run worktree:remove` deletes an additional Worktree's containers, image,
databases, accounts, and slot. Run it before `git worktree remove`; it refuses the
primary checkout. `mise run down` removes only this Worktree's containers;
`mise run infra:stop` stops the shared services after every Worktree is down.

A MariaDB image upgrade updates system tables on start. Delete the resulting
`storage/mariadb/system_mysql_backup_*.sql.zst` once verified.

## Orca

`orca.yaml` runs setup and removal and makes agents wait for setup. Orca reads it
from the new checkout, so commit it first. Let Orca use the repository commands, not
local-only ones, and give each Worktree its own browser profile: ports do not isolate
cookies.

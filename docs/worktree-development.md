# Worktree development

Worktrees share services as [TADR-0014](adr/template/0014-shared-worktree-infrastructure.md)
describes. `mise run setup` prepares a Worktree, `mise run dev:info` shows its URLs, and `mise run worktree:remove` deletes an additional
Worktree's containers, databases, accounts, and port slot; run it before `git worktree remove`.

`DEV_PORT_BASE` reserves a 100-port block per application: 20000 for the template, and an unused
block such as 20100 for each derived app. Keep `DB_URL` empty; it would override the Worktree's
database. Do not share `.env`, `storage`, `vendor`, `node_modules`, caches, or build output between
Worktrees, and give each Worktree its own browser profile.

A MariaDB image upgrade updates system tables on start. Delete the resulting
`storage/mariadb/system_mysql_backup_*.sql.zst` once verified.

Orca reads `orca.yaml` from the new checkout, so commit changes to it before creating a Worktree.

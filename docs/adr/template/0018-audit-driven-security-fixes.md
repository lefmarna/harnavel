# Audit-driven security fixes

Lockfile audits are the only check covering indirect Composer and pnpm dependencies on every
branch. Renovate updates only direct dependencies one PR at a time, and Dependabot security updates
target only the default branch without cross-ecosystem grouping or reliable indirect-dependency
fixes, so an unrelated advisory deadlocked fixes. The `security` workflow therefore fixes every
resolvable advisory in one commit per branch while Renovate handles routine updates.

Automated fixes change lockfiles only within declared constraints so shared constraints stay with
the template; advisories requiring a declaration change open a branch issue. `security-fix` lands
only after the full quality run passes on that exact commit. Audits compare against a baseline
rather than failing on pre-existing advisories: `security-fix` rejects only advisories absent from
the target branch (so one unfixable advisory cannot block the rest), quality runs compare against
the first parent (so existing advisories do not stall unrelated work or encourage ignore lists),
and `delivery` compares against the environment's last successful deployment.

Because `GITHUB_TOKEN` events trigger no workflows, `security-fix` fast-forwards the branch itself
and dispatches `delivery` without extra secrets; protected branches keep the verified commit on
`security-fix/<branch>` for manual merge. `develop` merges the default branch first so releases
cannot revert fixes, auto-resolving lockfile conflicts only when all default-branch lockfile edits
came from `security`.

Trade-offs: fixes land unreviewed; unresolved advisories rely on their issue (and a `develop`-only
advisory blocks release at `delivery`); the template maintains the remediation scripts; daily scans
cost roughly one runner minute per private repository; and `GITHUB_TOKEN` cannot merge workflow
file changes.

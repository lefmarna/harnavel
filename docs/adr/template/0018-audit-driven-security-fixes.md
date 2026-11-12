# Audit-driven security fixes

Lockfile audits are the only check that sees indirect Composer and pnpm dependencies on every
branch, and they gate every pull request. Renovate fixes only direct dependencies, one dependency
per pull request, so an indirect or unrelated advisory failed each fix's audit and left apps
exposed. Dependabot security updates target only the default branch, cannot update an indirect
dependency whose parent must change outside npm, and never group ecosystems, so the same deadlock
remains. The `security` workflow therefore fixes every advisory it can in one commit per branch, and
Renovate keeps only routine updates.

Fixes move resolutions only within declared constraints, so shared constraints stay with the
template; a fix that needs a declaration change becomes an issue. `security-fix` applies a fix only
after the full quality run passes on that exact commit. Its audit rejects only advisories the
target branch lacks, so one unfixable advisory cannot hold back the rest. Other quality runs reject
only advisories the first parent lacks: failing every change over an existing advisory would stop
unrelated merges and deployments, and invite ignore lists, without reducing exposure. `delivery`
rejects advisories its environment's last successful deployment lacks, because a release can skip
its checks and reuse a build, and a push can carry several commits or follow a refused one.

`GITHUB_TOKEN` events start no workflows, so `security-fix` fast-forwards the branch itself and
dispatches `delivery`, which needs no stored credential. A dispatched run's checks do not count
toward a pull request's required checks, so a protected branch keeps the verified fix on
`security-fix/<branch>` for a person to merge. `develop` merges the default branch first, so a
release cannot revert a fix; a lockfile conflict resolves automatically only when every
default-branch lockfile change came from `security`, since keeping `develop`'s side drops the rest.

The trade-offs: fixes land without review; an unresolved advisory is kept visible only by its
issue, and one that only `develop` has stops its release at deployment; the workflows are scripts
the template maintains; the daily scan costs about one runner minute per private repository; and
`GITHUB_TOKEN`, lacking a workflows permission, may be refused a merge that changes workflow files,
which then needs a human.

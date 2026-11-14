# Template and application ownership

Shared behavior converges on the template: when apps solve the same problem differently, the
template adopts the best design and every app converges on it, so apps differ only where their
domain or presentation requires. Domain, presentation, environment values, App ADRs, coverage
floors, and mutation selections stay app-owned; mutation eligibility and the blocking score are
shared.

Harnavel's root `README.md` introduces the template, and `README.en.md` is its English translation;
the user guide lives in the repository's GitHub Wiki, which derived apps do not inherit. Apps keep
`README.en.md` deleted and use the separate
[README scaffold](../../../.agents/skills/project-docs-init/assets/README.md): text outside the
`app:overview`, `app:development`, and `app:notices` blocks is template-owned, and the block bodies
are app-owned. `LICENSE`, `.github/SECURITY.md`, and the `license` field in `composer.json` describe
only the published template; apps keep the files deleted and set the field to `proprietary`, since in
a private app they would imply an MIT license and a reporting channel that does not exist. Apps keep
`THIRD_PARTY_NOTICES.md`.

Dependency constraints follow the same split: a constraint the template declares is shared and apps
reproduce it verbatim, while a package only the app declares carries an app-owned constraint.
Lockfiles are app-owned because only the app knows its complete dependency graph, so convergence
means compatible shared behavior and dependency conditions, not identical resolved versions; the
template states required minimum versions and fixes explicitly instead of relying on its lockfile.
Template merges, routine maintenance, and unrelated security fixes stay separate changes so
regressions remain attributable.

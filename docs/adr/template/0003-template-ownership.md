# Template and application ownership

Shared behavior converges on the template; domain, presentation, environment values, and App ADRs
stay app-owned. Generic improvements return through the template to avoid divergent shared behavior:
when apps solve the same problem differently, the template adopts the best design and every app
converges on it, so apps differ only where their domain or presentation requires.
Coverage floors and mutation selections are app-owned; mutation eligibility and blocking score are shared.
Harnavel's root `README.md` introduces the template. Derived apps use the separate
[README scaffold](../../../.agents/skills/project-docs-init/assets/README.md): its development
guidance is template-owned, and the `app:overview`, `app:development`, and `app:notices` block bodies
are app-owned. Initialization fills those blocks; every template merge updates the surrounding
text from the current scaffold, including conflict-free merges and scaffold-only changes. Legacy
app READMEs retain their app-specific content in those blocks when first adopting the scaffold.
`LICENSE`, `.github/SECURITY.md`, and the `license` field in `composer.json` describe only the
published template; apps delete the files, keep them deleted, and set the field to `proprietary`,
since in a private app they would imply an MIT license and a reporting channel that does not exist.
Third-party notices live in `THIRD_PARTY_NOTICES.md`, which apps keep.

Dependency constraints follow the same split: a constraint the template declares is shared and apps
reproduce it verbatim, while a package only the app declares carries an app-owned constraint.
Lockfiles are app-owned because only the app knows its complete dependency graph, so convergence
means compatible shared behavior and dependency conditions, not identical resolved versions; the
template states required minimum versions and fixes explicitly instead of relying on its lockfile
to deliver them. Template merges, routine maintenance, and unrelated security fixes stay separate
changes so regressions remain attributable.

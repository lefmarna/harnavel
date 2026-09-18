# Template and application ownership

Shared behavior converges on the template; domain, presentation, environment values, and App ADRs
stay app-owned. Generic improvements return through the template to avoid divergent shared behavior.
Coverage floors and mutation selections are app-owned; mutation eligibility and blocking score are shared.

Dependency constraints follow the same split: a constraint the template declares is shared and apps
reproduce it verbatim, while a package only the app declares carries an app-owned constraint.
Lockfiles are app-owned because only the app knows its complete dependency graph, so convergence
means compatible shared behavior and dependency conditions, not identical resolved versions; the
template states required minimum versions and fixes explicitly instead of relying on its lockfile
to deliver them. Template merges, routine maintenance, and unrelated security fixes stay separate
changes so regressions remain attributable.

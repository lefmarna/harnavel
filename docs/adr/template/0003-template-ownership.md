# Template and application ownership

Shared behavior converges on the template; domain, presentation, environment values, and App ADRs
stay app-owned. Generic improvements return through the template to avoid divergent shared behavior.
Coverage floors and mutation selections are app-owned; mutation eligibility and blocking score are shared.

Dependency declarations combine shared requirements with app-specific consumers; lockfiles are
app-owned because only the app has its complete dependency graph. Template merges reconcile that
graph with minimal necessary changes, while routine maintenance and unrelated security fixes are
separate work so regressions remain attributable. Convergence requires compatible shared behavior
and dependency conditions, not identical resolved versions; the template must state required
minimum versions and fixes explicitly rather than relying on its lockfile to deliver them.

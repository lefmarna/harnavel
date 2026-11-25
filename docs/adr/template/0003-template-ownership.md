# Template and application ownership

Shared behavior converges on the template: when derived apps solve a generic problem differently,
the template adopts the best design and apps converge on it, leaving apps to differ only in domain,
presentation, environment values, App ADRs, coverage floors, and `mutates()` selections (mutation
eligibility rules and the blocking score stay shared).

Harnavel's root `README.md` / `README.en.md` and GitHub Wiki belong to the template only. Derived
apps delete `README.en.md`, `LICENSE`, and `.github/SECURITY.md`, set `composer.json`'s `license`
to `proprietary` (avoiding a false MIT grant or reporting channel), keep `THIRD_PARTY_NOTICES.md`,
and build their `README.md` from the
[README scaffold](../../../.agents/skills/project-docs-init/assets/README.md) where only the
`app:overview`, `app:development`, and `app:notices` block bodies are app-owned.

Shared dependency constraints stay verbatim in derived apps while lockfiles stay app-owned, since
only an app knows its full dependency graph; required minimums and fixes are therefore stated as
explicit constraints rather than left to the template's lockfile. Template merges, routine updates,
and security fixes stay separate commits so regressions remain attributable.

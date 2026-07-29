# Tests are the executable specification

Behavioral changes are implemented test-first. An agent normally continues from the test through
implementation and verification; it pauses only when behavior is ambiguous or the user requests
tests alone. Review begins with test cases, assertions, and automated gates, but authentication,
secrets, CI and delivery, migrations, concurrency, outbound side effects, and gate changes also
require implementation review because a green suite cannot reveal cases nobody specified.

Feature tests are the canonical server and Inertia contract. Playwright is limited to smoke coverage
and critical user flows. Coverage measures reach, while mutation testing checks the selected tests'
defect-detection strength; neither substitutes for judgment. Dependency advisories are reported by
GitHub Dependabot Alerts on the default branch rather than blocking an unchanged commit in CI.

# Use Kiro discovery for new work

> **Amended by [ADR-0002](0002-tests-as-executable-specification.md).** New work is now
> test-first driven; `kiro-discovery` is no longer the mandatory entry point. Kiro is an
> optional pre-design step for large/risky features and a post-hoc organization step.
> The text below is retained for historical context.

Use `kiro-discovery` as the discovery entry point for raw new-work ideas, then use the Kiro requirements and design phases to turn the chosen boundary into implementation-ready specs. A standalone pre-spec requirements discovery workflow would duplicate purpose, alternatives, and scope discovery while creating a second session-artifact handoff into Kiro, so this project keeps discovery in the Kiro flow; use `grill-with-docs` only when an existing plan, spec, design draft, or proposed decision needs to be challenged against project language, code, and documented decisions.

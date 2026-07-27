# Shared-surface convergence for derived projects

## Status

Accepted.

## Context

Multiple projects derive from this template and adopt its updates via `git pull template main` +
`/merge-template` (see [`TADR-0001`](0001-development-workflow.md)). Shared template-origin code
and docs naturally drift when apps fix the same problem locally. Unmanaged drift makes future
template pulls expensive and judgment-heavy.

## Decision

The template is the canonical source for template-origin behavior. Improvements that mature in
an app are upstreamed to the template when they are not app-specific; the best version wins.

Files are treated in three categories:

- **Shared** — no legitimate app-specific content. Keep byte-identical across template and apps.
- **Hybrid** — template scaffolding and app content coexist. Converge only the template-origin
  hunks; preserve app hunks.
- **App-owned** — domain, presentation, branding, environment, and app-specific configuration.
  Do not converge through the template.

Coverage and mutation gate values, mutation blocking, and the density of
`@pest-mutate-ignore` annotations are calibrated per app. The shared workflow structure may
converge, but the calibrated lines and adjacent rationale stay app-priority.
The bounded in-flight request drain in `delivery.yml` follows the same rule: its lifecycle step is
shared, while its duration and adjacent rationale are calibrated to each app's longest synchronous
request.

Calibration stays inline in the pulled file. A calibration whose in-file value would also seed a
newly derived application is the one case where that fails — the template cannot simultaneously hold
a correct runtime value for its own environments and a safe initial value for a new app — and such a
value belongs in per-environment configuration instead, with the workflow defaulting to the safe
setting when none is supplied. **No current calibration needs this**, and none should be introduced
casually: configuration outside the file cannot be reviewed in the diff, and each such value becomes
a manual step every environment must complete before it can deploy. Reach for it only when a value
genuinely carries both roles at once.

The operational path classification and conflict-resolution procedure live in
`/merge-template` and `/absorb-from-apps`; this ADR records the decision model only.

## Considered alternatives

- **Extract every calibration value out of the pulled files** (GitHub Actions `vars`/inputs) so the
  workflow YAML is byte-identical. Rejected: more CI wiring, and an inline value is easier to read
  and review. The narrow exception above is not this alternative — it moves one value because its
  in-file default would seed new applications wrongly, not to make the file byte-identical.
- **Unify the thresholds across all projects.** Rejected: it contradicts TADR-0001's per-project
  calibration — a content app and a compiler service have different defensible mutation-score
  ceilings.
- **Keep an exhaustive manifest of shared paths.** Rejected: it duplicates the decision model,
  reads like tool configuration, and becomes another artifact to keep synchronized.
- **Converge everything, including app-owned files.** Rejected: presentation, branding, domain,
  and app-specific configuration are intentionally app-specific; forcing byte-identity there
  creates churn without propagation benefit.

## Consequences

- Shared tests and the production code they specify move together: a stricter shared test is
  upstreamed only with implementation that keeps the template green.
- App-specific calibration remains visible in the app's workflow files instead of being hidden
  behind external defaults. Should the exception above ever apply again, the value would sit outside
  the file, so the workflow must echo its resolved setting to the run log to keep the effective
  calibration observable per run rather than in the diff.

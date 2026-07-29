# Shared-surface convergence

The template is canonical for template-origin behavior. Shared files converge to the template;
hybrid files take template-origin hunks while preserving app content; domain, presentation,
branding, environment values, and App ADRs remain app-owned. Improvements developed in an app move
upstream when they are generic, then return through the normal template merge.

Coverage floors remain app-calibrated. Mutation testing uses a shared blocking threshold of 100%
and a shared eligibility rule, while each app owns the `mutates()` declarations naming its
deterministic, observable logic. Operational merge details belong in the merge skill or the
specific downstream request, not in this ADR.

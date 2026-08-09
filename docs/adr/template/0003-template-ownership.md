# Template and application ownership

The template owns template-origin behavior: shared files converge to it, hybrid files take its
hunks while preserving app content, and domain behavior, presentation, branding, environment
values, and App ADRs stay app-owned. Generic improvements move from an app into the template before
returning through a normal merge. Coverage floors and `mutates()` selections stay app-owned, while
the mutation eligibility rule and blocking score remain shared.

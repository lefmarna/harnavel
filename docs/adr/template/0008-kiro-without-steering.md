# Kiro specs without steering documents

Kiro reads the codebase plus `AGENTS.md`, `CONTEXT.md`, `docs/generated/boost.md`, and relevant ADRs
instead of `.kiro/steering/`, whose generated summaries are a lossier copy of those sources.
Because cc-sdd owns both skill trees, Kiro templates, and `docs/generated/specs.md`, an upstream
refresh reviews them as one vendor update and reapplies this removal. That refresh is the only time
`docs/generated/specs.md` is hand-edited; it is never updated independently.

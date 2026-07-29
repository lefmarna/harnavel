# Architecture Decision Records

Template ADRs live under `docs/adr/template/` and use the `TADR-NNNN` namespace. Derived
applications take them from the template and do not edit them. App-specific ADRs live directly
under `docs/adr/` and use `ADR-NNNN`.

Create an ADR only when a decision is hard to reverse, surprising without context, and the result
of a real trade-off. Keep it as short as possible: the current decision and enough rationale to
prevent the decision being repeated. Tests specify behavior; `AGENTS.md` holds every-session
constraints; `CONTEXT.md` is only a domain glossary; procedures and external facts belong in other
docs.

Delete proposed or temporary decisions that never acquired lasting consequences. When a current
decision replaces an older one, merge the useful rationale and remove the history unless another
record still depends on it. Template ADR numbers are never reused.

During a template merge, preserve the application's flat ADR files and the app-owned index below.

## App ADRs

This section is app-owned.

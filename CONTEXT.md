# Domain Glossary

The ubiquitous language of this project: what each domain term means, in the domain's
own words. Read it before planning, specifying, designing, or refactoring an area; update
it in the organize step when work changes domain language
([`TADR-0001`](docs/adr/template/0001-development-workflow.md)).

Rules:

- Domain language only — no implementation details, file paths, or class names.
- Current state only — rewrite superseded meanings instead of appending history.
- Term entries may use the language the domain speaks (Japanese is fine); keep this
  header as is.
- App-owned: seeded by the template, never merged back to it. Split into `CONTEXT-MAP.md`
  plus per-context files only when one glossary no longer fits one bounded context.

Entry format:

```markdown
## <Term>

<One-paragraph definition. Add synonyms, or what the term explicitly does NOT mean, only
when confusion is a real risk.>
```

---

_No terms yet — the template has no domain. Entries appear once a derived project defines
its first term (seeded via `project-docs-init`)._

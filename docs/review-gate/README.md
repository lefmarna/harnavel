# Review Gate

## Workflow

```text
codex-review SKILL -> findings -> fix -> re-run -> ok: true
```

**When to run**: After spec/plan updates, after major steps (>= 5 files / public API / infra-config), before commit/PR/release.

Note: saved review JSON/log files (`*_summary.md`) may contain Japanese text depending on reviewer output requirements.

## Manual Review Checklist

These items require human/AI judgment (automated checks like ESLint, Pint, and TypeScript are handled by [verification commands](../../AGENTS.md#verification-required-after-every-implementation)).

### Architecture

- [ ] **Layers**: 3-layer pattern followed (Controller -> Service -> Repository)
- [ ] **Data access**: No direct model queries outside repositories
- [ ] **Controller**: Form Request + API Resource or Inertia render

### Security

- [ ] **Secrets**: No secrets in committed files
- [ ] **Validation**: Form Requests for all validation (no inline)
- [ ] **N+1**: No N+1 query patterns introduced

### Quality

- [ ] **Tailwind**: v4 syntax (not v3)
- [ ] **Factory**: Used for all models in tests
- [ ] **Docs**: Updated for public API changes
- [ ] Generated files are not manually edited (`docs/generated/boost.md`, `docs/generated/specs.md`)

### Deep-Dive References

- [Backend rules](../guides/backend.md)
- [Frontend rules](../guides/frontend.md)
- [Infrastructure rules](../guides/infrastructure.md)
- [Commands](../references/commands.md)
- [Schema reference](../references/schema-design-spec.md)

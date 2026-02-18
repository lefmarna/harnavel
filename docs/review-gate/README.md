# Review Gate

## Workflow

```
codex-review SKILL → findings → fix → re-run → ok: true
```

**When to run**: After spec/plan updates, after major steps (>= 5 files / public API / infra-config), before commit/PR/release.

## Manual Review Checklist

These items require human/AI judgment (automated checks like ESLint, Pint, and TypeScript are handled by [verification commands](../../AGENTS.md#verification-required-after-every-implementation)).

### Architecture

- [ ] **Layers**: 3-layer pattern followed (Controller → Service → Repository)
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

### Deep-dive References

Per-document checklists for focused review: [architecture](../backend/architecture.md#verification-checklist), [api](../backend/api.md#verification-checklist), [database](../backend/database.md#verification-checklist), [security](../backend/security.md#verification-checklist), [testing](../backend/testing.md#verification-checklist), [error handling](../backend/error-handling.md#verification-checklist), [design](../frontend/design.md#verification-checklist), [SEO](../frontend/seo.md#verification-checklist), [E2E testing](../frontend/e2e-testing.md#verification-checklist).

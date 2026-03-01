# Review Gate

## Workflow

```text
codex-review SKILL -> findings -> fix -> re-run -> ok: true
```

**When to run**: After spec/plan updates, after major steps (>= 5 files / public API / infra-config), before commit/PR/release.

## Documentation Exception

For docs-only changes, do not request `codex-review` immediately after editing.
First, wait for explicit user confirmation that the documentation draft is ready for review.

Recommended flow for docs-only work:

```text
edit docs -> user confirms content -> codex-review SKILL -> findings -> fix -> re-run -> ok: true
```

Saved review JSON/log files (`*_summary.md`) may contain Japanese text depending on reviewer output requirements.

## Review Focus

- Respect architecture boundaries from the task-specific guides.
- Check for security regressions and missing validation.
- Check for missing tests and missing docs updates.
- Confirm generated files were not edited manually.

## Manual Review Checklist

Use this checklist to define review-ready status before or alongside `codex-review`.

### Architecture

- [ ] The change respects the architecture and responsibility boundaries in the relevant task guide.
- [ ] No new direct data-access or routing shortcuts bypass the documented project rules.

### Security

- [ ] Validation, authorization, and secret handling did not regress.
- [ ] No obvious N+1 or unsafe data exposure was introduced.

### Quality

- [ ] Required tests were added or updated, or the omission is intentional and understood.
- [ ] Required docs were updated when behavior, API, or workflow changed.
- [ ] Generated files were not edited manually (`docs/generated/boost.md`, `docs/generated/specs.md`).
- [ ] For docs-only changes, user confirmation was obtained before running `codex-review`.

## References

- [Backend rules](../guides/backend.md)
- [Frontend rules](../guides/frontend.md)
- [Infrastructure rules](../guides/infrastructure.md)
- [Commands](../references/commands.md)
- [Schema reference](../references/schema-design-spec.md)

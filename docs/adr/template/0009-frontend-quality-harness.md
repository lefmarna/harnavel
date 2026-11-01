# Frontend quality harness

Vite+ is the sole frontend toolchain owner to avoid overlapping fixers and incompatible Vite plugin
types. Native rollback and symlink exclusion are preferred over compatibility code, and
whole-project hook checks stay read-only because Vite+ restages task edits to any tracked file.

Deterministic correctness gates stay regardless of coding-agent capability. An added lint rule must
catch concrete defects beyond existing checks without routinely forcing boilerplate or suppressions
for valid code; broad advisory categories stay opt-in per app because their false positives and
workaround costs vary. Global warning failures and blanket compiler-rule pinning are rejected
because upstream reclassifications would turn advisory diagnostics into build failures.

Type configurations model each transformation host and runtime API so Node globals cannot leak into
browser code. `exactOptionalPropertyTypes` is rejected because React and Inertia props routinely pass
explicit `undefined`.

# Frontend quality harness

Vite+ is the sole toolchain owner to avoid overlapping fixers and incompatible Vite plugin types.
Formatting, linting, and runtime-specific type checks remain independent. Global warning failures
and blanket compiler-rule pinning would turn advisory diagnostics and upstream reclassifications
into build failures.

Deterministic correctness gates remain necessary regardless of coding-agent capability. Additional
lint rules must detect concrete defects beyond existing checks without routinely forcing boilerplate
or suppressions for valid code. Broad advisory categories remain opt-in because their false positives
and workaround costs vary across derived apps.

Types model transformation hosts and runtime APIs, preventing Node globals from leaking into browser
code. Property-presence strictness is rejected because React/Inertia props routinely pass explicit
`undefined`. Upgrades re-evaluate diagnostics, native dependencies, and dependency-policy defaults.

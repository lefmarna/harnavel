# Frontend quality harness

Oxfmt is the sole formatting owner and follows its syntax defaults. Repository-specific settings
are limited to import and Tailwind class sorting, Markdown embedded-code safety, and generated or
vendored paths. Oxlint is the sole linter: its default correctness rules are supplemented by stable,
type-aware Promise, unsafe-value, switch-exhaustiveness, and React Hooks checks. TypeScript remains
the independent compilation gate, and tests specify behavior.

The correctness category is explicitly an error-level gate. Oxlint enables that category as
warnings by default, which reports defects without failing CI; a global deny-warnings flag is not
used because React Compiler diagnostics intentionally remain advisory. Formatter behavior tests use
stdin fixtures so parallel lint and type checks never observe invalid transient source files.

ESLint and Prettier are not retained as residual tools. Their overlapping configs, fix order, and
dependency surface cost more than the remaining stylistic checks are worth for an AI-oriented
harness. Local hooks use only safe Oxlint fixes before Oxfmt, then verify without mutation; CI runs
the same format, lint, type, and test responsibilities across the whole repository.

Oxlint, Oxfmt, `oxlint-tsgolint`, and TypeScript form an exact-pinned, verified quality-gate
baseline. The TypeScript pin stabilizes compiler diagnostics and its native package surface; it is
not a direct Oxlint compatibility requirement. Updating any member is a deliberate harness
migration with supply-chain policy, config-behavior, and full-gate verification rather than an
incidental dependency refresh.

Platform-specific Rollup, Tailwind Oxide, and Lightning CSS packages remain transitive. Vite and
Tailwind own the binaries they use, while pnpm's frozen lockfile records the platform variants.
Direct optional dependencies inherited from the npm-oriented starter would duplicate that
ownership, and Vite 8 no longer uses the legacy Rollup binary at runtime.

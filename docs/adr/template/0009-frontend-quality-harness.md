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

The linter rejects file-wide TypeScript checking bypasses and `@ts-ignore`, while documented,
localized `@ts-expect-error` remains available. It also rejects the misleading `{}` and `Function`
types because both discard useful type contracts. Discriminated-union switches must name every
member even when they contain a runtime fallback; a `default` branch therefore cannot hide a newly
added union member from the exhaustiveness gate.

Production code under `resources/js` cannot import Node.js builtin modules, whether they use the
`node:` protocol or a bare specifier. Vite does not provide those runtime modules in browsers.
Frontend unit tests are exempt because they execute in Node; scripts and Playwright remain separate
Node-capable programs.

ESLint and Prettier are not retained as residual tools. Their overlapping configs, fix order, and
dependency surface cost more than the remaining stylistic checks are worth for an AI-oriented
harness. Local hooks use only safe Oxlint fixes before Oxfmt, then verify without mutation; CI runs
the same format, lint, type, and test responsibilities across the whole repository.

TypeScript uses four programs because the repository has four runtime contexts: production browser
code, Node-based frontend unit tests that import browser modules, Node/tsx tooling (including
`vite.config.ts`), and Playwright tooling (including `playwright.config.ts`). Production browser code
sees Vite and DOM declarations but not Node globals. The unit program also loads the browser
program's shared declarations. The scripts program remains Node-only, while Playwright includes DOM
declarations because its callbacks can execute in the browser. A coverage test requires every
first-party implementation file to be a root file of exactly one program; ambient declarations may
be shared but must belong to at least one program. A newly added file therefore cannot silently miss
the compiler gate.

All programs use a stable ES2023 language target and `module: Preserve`; the latter implies bundler
resolution and matches the actual Vite, tsx, and Playwright transformation hosts better than
NodeNext's native-Node extension rules. Vite, not `tsc`, owns the final browser syntax transform.
The browser contract also includes ES2023 runtime builtins without adding polyfills. Its explicit
Vite target is Vite 8's Baseline Widely Available set with Firefox raised from 114 to 115, the last
gap for ES2023 array copy methods. A behavior test resolves the actual Vite config and compiles a
representative builtin, so changes to either side of this contract must remain deliberate.

The compiler gate explicitly enforces isolated, verbatim, erasable-only TypeScript, unchecked-index
safety, complete returns, explicit overrides, and resolvable side-effect imports.
`exactOptionalPropertyTypes` is not enabled because React/Inertia props routinely pass
`T | undefined` without depending on property-presence semantics. Index-signature property syntax
is likewise not a safety boundary. Switch fallthrough remains solely in Oxlint, where an explicit
fallthrough comment can distinguish deliberate control flow from a missing `break`.

Oxlint, Oxfmt, `oxlint-tsgolint`, and TypeScript form an exact-pinned, verified quality-gate
baseline. The TypeScript pin stabilizes compiler diagnostics and its native package surface; it is
not a direct Oxlint compatibility requirement. Updating any member is a deliberate harness
migration with supply-chain policy, config-behavior, and full-gate verification rather than an
incidental dependency refresh.

The dependency-age policy comes from pnpm 11's built-in 24-hour default, not from user or global
configuration. Its built-in mode is non-strict: pnpm prefers an old-enough version in the requested
range but may fall back to a younger version when no candidate satisfies the age threshold. It is
therefore a risk-reduction policy, not an unconditional rejection gate. The repository reproduces
these defaults by exact-pinning pnpm through mise and CI. It does not repeat `minimumReleaseAge` in
`pnpm-workspace.yaml`, because explicitly setting that value also enables strict mode by default. A
pnpm upgrade must re-evaluate both the age and strictness defaults as part of the deliberate
toolchain migration.

Platform-specific Rollup, Tailwind Oxide, and Lightning CSS packages remain transitive. Vite and
Tailwind own the binaries they use, while pnpm's frozen lockfile records the platform variants.
Direct optional dependencies inherited from the npm-oriented starter would duplicate that
ownership, and Vite 8 no longer uses the legacy Rollup binary at runtime.

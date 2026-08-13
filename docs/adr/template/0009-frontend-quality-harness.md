# Frontend quality harness

Oxfmt solely owns formatting, Oxlint solely owns linting, TypeScript independently checks
compilation, and tests specify behavior. Oxlint's correctness category is an error gate, but
warnings are not denied globally because React Compiler diagnostics remain advisory. Repository
policy stays in those tools' configs; ESLint, Prettier, and overlapping fix pipelines are rejected.
Production browser code cannot import Node.js builtins, while unit tests, scripts, and Playwright
receive only the ambient libraries their execution contexts require.

TypeScript uses separate programs for production browser code, Node-based frontend unit tests,
Node/tsx tooling, and Playwright, with every source file covered by an applicable program. Programs
use stable ECMAScript editions and `module: Preserve` because Vite, tsx, and Playwright are the
transformation hosts; NodeNext's native-Node extension rules model a different environment.
`exactOptionalPropertyTypes` remains disabled because React/Inertia props routinely pass
`T | undefined` without relying on property-presence semantics. Vite owns production syntax
compatibility, while runtime and API compatibility remain test concerns or explicit application
support decisions.

Oxlint, Oxfmt, `oxlint-tsgolint`, TypeScript, and pnpm form an exact-pinned, verified toolchain.
Upgrades are deliberate harness migrations that re-evaluate diagnostics and native packages.
pnpm's built-in dependency-age policy remains non-strict; `minimumReleaseAge` is not duplicated
because declaring it also enables strict mode by default, so each pnpm upgrade must re-evaluate both
defaults. Platform-specific Rollup, Tailwind Oxide, and Lightning CSS packages remain transitive
dependencies owned by Vite and Tailwind and recorded in the frozen lockfile.

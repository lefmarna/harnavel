# Frontend quality harness

Vite+ (`vite-plus`) is the single frontend toolchain: it bundles Vite, Oxlint, `oxlint-tsgolint`,
and Oxfmt at versions it pins itself, so one exact `vite-plus` pin fixes the whole set. Oxfmt
solely owns formatting, Oxlint solely owns linting, TypeScript independently checks compilation,
and tests specify behavior. Repository lint and format policy lives in the `lint` and `fmt` blocks
of `vite.config.ts`, which take the Oxlint and Oxfmt configuration schemas verbatim; ESLint,
Prettier, and overlapping fix pipelines are rejected. `vite` stays declared but a
`vite@*` override aliases it to `@voidzero-dev/vite-plus-core`, because a second Vite copy splits
the plugin type graph and breaks type checking; the unmet-peer warnings that alias produces are
expected. Vite+'s composite `vp check` and its `denyWarnings` default are not adopted: warnings
stay undenied, and `vp fmt`, `vp lint`, and `types:check` remain separately invocable gates.

Oxlint's correctness category is an error gate, and warnings are not denied globally. React
Compiler diagnostics arrive as individually classified rules rather than as one advisory rule, and
that classification is Oxlint's to make: the category gate follows it instead of pinning rules by
name. Naming a rule outside the gate buys coverage at the cost of a rule that can be renamed into a
parse error or reclassified into silence, so only `react/capitalized-calls` is named — the rest of
what falls outside correctness is either already reported by a rule this config gates at error or
is a compiler-bailout notice rather than a defect. Production browser code cannot import Node.js
builtins, while unit tests, scripts, and Playwright receive only the ambient libraries their
execution contexts require.

TypeScript uses separate programs for production browser code, Node-based frontend unit tests,
Node/tsx tooling, and Playwright, with every source file covered by an applicable program. Vite+'s
own type-check step is left off because it is one program over the whole tree. Programs use stable
ECMAScript editions and `module: Preserve` because Vite, tsx, and Playwright are the transformation
hosts; NodeNext's native-Node extension rules model a different environment.
`exactOptionalPropertyTypes` remains disabled because React/Inertia props routinely pass
`T | undefined` without relying on property-presence semantics. Vite owns production syntax
compatibility, while runtime and API compatibility remain test concerns or explicit application
support decisions.

`vite-plus`, TypeScript, and pnpm are exact-pinned. Upgrades are deliberate harness migrations that
re-evaluate diagnostics and native packages. pnpm's built-in dependency-age policy remains
non-strict; `minimumReleaseAge` is not duplicated because declaring it also enables strict mode by
default, so each pnpm upgrade must re-evaluate both defaults. Platform-specific Rollup, Tailwind
Oxide, and Lightning CSS packages remain transitive dependencies owned by Vite and Tailwind and
recorded in the frozen lockfile.

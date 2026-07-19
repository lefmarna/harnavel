# Inference-first model property types

## Status

Accepted.

## Context

Eloquent models do not declare property types in PHP, and the classic remedy is an exhaustive
`@property` docblock per model. Larastan treats such annotations as authoritative: a `@property`
line overrides whatever the analyzer could infer, so a wrong annotation silently replaces a
correct inference. The template's own `User` model demonstrated the failure: its docblock claimed
mutable `Illuminate\Support\Carbon` while `AppServiceProvider` configures
`Date::use(CarbonImmutable::class)`, so runtime values are `Carbon\CarbonImmutable` — and
level-max analysis stayed green on the wrong types.

Larastan can instead infer property types from two sources it already understands: migration
scanning for the persisted column shape, and cast definitions for runtime refinement. Larastan 3
only reads the legacy `$casts` property by default; parsing Laravel's `casts()` method bodies
requires `parseModelCastsMethod: true`. Without the flag, datetime columns cast via `casts()`
silently degrade to `string|null` inference while analysis still passes.

The convention was validated as spikes in three derived repositories before being adopted here,
and one of those spikes surfaced the incorrect Carbon annotation above.

## Decision

Model property type information is inference-first:

- **Migrations describe the persisted column shape.** They are the single source for column base
  types and nullability; do not restate them in PHPDoc.
- **`casts()` refines runtime types and must directly return an array literal.** Larastan only
  parses a `casts()` body whose return expression is itself an array literal; its keys and values
  may use statically resolvable constants and expressions. Returning a class constant as the whole
  array, conditional returns, incremental construction, and runtime configuration are unsupported.
- **`parseModelCastsMethod: true` is mandatory in `phpstan.neon.dist`.** An architecture test
  guards the flag because removing it degrades inference without failing analysis.
- **`@property` PHPDoc is the exception, not the default.** An annotation is retained only for a
  proven inference gap — for example a raw column, an exact JSON array shape, a lifecycle state
  before persistence, or a column whose migration history or Blueprint helper defeats scanning —
  and must be complete, truthful, narrowly scoped, and accompanied by the reason it cannot be
  inferred.
- **Globally false relationship non-null assertions are prohibited.** A relationship that is
  non-null only for a particular query or service is refined at that usage boundary, not asserted
  on the model.

The template `User` model carries no exhaustive `@property` block — only reasoned annotations for
proven inference gaps — and is the convention-current exemplar required by
[`TADR-0005`](0005-layer-decision-rules.md).

## Considered alternatives

- **Keep exhaustive per-model `@property` docblocks.** Rejected: they override correct inference
  and drift silently, as the template's own Carbon annotation proved.
- **Generate docblocks with an IDE-helper dependency.** Rejected: it adds a dependency and a
  second generated source of truth to regenerate and review, for types Larastan already infers.
- **Prohibit `@property` entirely.** Rejected: some values are genuinely not inferable, and a
  truthful, narrowly scoped annotation beats a wrong inferred type.
- **Keep Larastan's default cast handling.** Rejected: with `casts()` methods it silently degrades
  datetime properties to `string|null` while level-max analysis stays green.

## Consequences and accepted risks

- Removing authoritative docblocks lets incompatible inferred types surface at analyzed usages
  instead of hiding behind PHPDoc, and every retained annotation is a small, reviewable exception
  with its reason.
- `phpstan.neon.dist` becomes a hybrid convergence surface under
  [`TADR-0003`](0003-shared-surface-convergence.md): the flag and its reason comment are
  template-origin, while analysis paths and other calibration remain app-owned.
- Derived apps adopting the convention must enable the flag before deleting docblocks, or their
  datetime properties silently degrade to `string|null`.
- Inference quality now depends on migrations and `casts()` staying analyzable; a model whose
  types Larastan cannot resolve is fixed at the source (migration scanning, cast expression)
  before an annotation is considered.

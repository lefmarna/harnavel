# Inference-first model property types

Eloquent model property types come from migrations and `casts()`, with
`parseModelCastsMethod: true` kept in `phpstan.neon.dist`. A `casts()` method directly returns an
array literal so Larastan can analyze it. Exhaustive `@property` blocks are not maintained because
they override inference and can silently drift; retain an annotation only for a demonstrated
inference gap and state that reason. Refine relationship nullability at the usage boundary rather
than asserting a globally false model type.

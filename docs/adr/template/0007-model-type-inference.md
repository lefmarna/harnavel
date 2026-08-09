# Eloquent model type inference

Model property types come from migrations and an array-literal `casts()` method, with
`parseModelCastsMethod: true` retained for Larastan. Exhaustive `@property` blocks are rejected
because they override inference and drift; annotations remain only for demonstrated gaps with the
reason stated. Relationship nullability is refined at the usage seam rather than asserted as a
globally false model type.

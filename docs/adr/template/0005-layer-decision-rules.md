# Layer decision rules: when to introduce Service, Repository, Params, and ViewModel

## Status

Accepted. The rules are ratified and the template's initial rollout has migrated the pre-existing
exemplars that contradicted them.

## Context

The template ships two reference implementations that send contradictory signals about
when a backend layer object is warranted:

- **Google account linking** is fully layered: a `GoogleAuthService` orchestrates a locked
  `DB::transaction`, a `UserRepository` mediates persistence, a `UserRepositoryContract` is
  bound in the container, and typed exceptions carry control flow. It touches multiple rows,
  external OAuth identities, and transactional email.
- **Profile update** is a single-model CRUD: `ProfileController::update` fills a `User` from
  `validated()`, detects an email change inline, and saves. Before this decision it routed
  through an `UpdateProfileParams` object whose only job was to copy two already-validated keys
  into an array. There was no service, no repository, and no transaction. The Params object
  added a layer without adding a boundary.

Nothing states which shape a new use case should take. In AI-driven development this
ambiguity is expensive: different agents (and different derived apps) resolve the same
situation differently, so the template — the thing every derived app imitates — teaches
drift. The remedy is an explicit decision rule for each layer, anchored to the exemplar that
demonstrates it.

The layers under decision:

- **Service** (`app/Services/*`) — a use-case orchestrator behind a `...ServiceContract`,
  bound as a singleton.
- **Repository** (`app/Repositories/*`) — persistence mediation behind a
  `...RepositoryContract`.
- **Params** (`app/Params/*`) — a `final readonly` value object, framework-independent
  (it must not depend on `Illuminate\Http\Request`, `FormRequest`, or the session; the
  `App\Params` arch rule enforces this).
- **ViewModel** (`app/ViewModels/*`) — assembles the data a page or fragment renders.

## Decision

### 1. Allow controller-direct Eloquent for simple CRUD

For a single-model create/update/delete with no transaction, no cross-model coordination,
and no external effect, the controller uses `validated()` directly against the model. Do
**not** introduce a Service, a Repository, or a Params object for this shape. `ProfileController`
is the exemplar: it stays a plain controller. Keep an array-shape `@var` annotation on
`validated()` so Larastan checks the keys.

### 2. Introduce a Service (with a Repository) for orchestrated use cases

Introduce a Service when the use case involves any of: a database transaction, row locking,
coordination across more than one model, an external API, or sending mail. `GoogleAuthService`
is the exemplar. The Service holds the orchestration behind a `...ServiceContract`, and a
`...RepositoryContract` mediates its persistence; both are container-bound singletons (enforced
by the binding-completeness test).

Whether the Service also takes a Params object is decided by rule 3, not by this rule: add a
Params-(a) when the Service's input is a form-submitted write payload worth typing. A Service
that consumes a handful of scalar arguments does not need one — `GoogleAuthService::findOrCreate`
takes the OAuth identity fields (`googleId`, `email`, `name`) straight from the provider callback,
where there is no FormRequest boundary, so it has no Params.

Fortify's Action classes (`app/Actions/Fortify/*`, e.g. `CreateNewUser`, `ResetUserPassword`) are
a framework-defined variant of this rule: Fortify fixes the Action as the orchestration entry point
for its auth use cases, so the Action consumes the `...RepositoryContract` directly, the same way a
Service does. Wrapping it in an app Service would add indirection without a boundary, so it stays an
Action. Read "Service" throughout these rules as "a Service, or an equivalent framework-mandated
Action orchestrator."

### 3. Params exist in exactly two forms — never a third

A Params object is created only in one of these two shapes. If a use case fits neither, it
does not get a Params object.

**(a) Write DTO at the Controller to Service boundary.** The typed input a Service consumes.
It is created **only when a Service consumes it** — a Params without a Service (today's
`UpdateProfileParams`) is disallowed. The FormRequest owns the conversion via
`toParams(): XxxParams`, so the array-shape cast lives once, next to the validation rules that
justify its shape, instead of being re-asserted in the controller:

```php
// app/Http/Requests/Foo/CreateFooRequest.php
final class CreateFooRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toParams(): CreateFooParams
    {
        /** @var array{name: string, amount: int} $validated */
        $validated = $this->validated();

        return new CreateFooParams(
            name: $validated['name'],
            amount: $validated['amount'],
        );
    }
}

// app/Params/CreateFooParams.php
final readonly class CreateFooParams
{
    public function __construct(
        public string $name,
        public int $amount,
    ) {}
}

// app/Http/Controllers/FooController.php
public function store(CreateFooRequest $request, FooServiceContract $service): RedirectResponse
{
    $service->create($request->toParams());

    return to_route('foo.index');
}
```

The Params is a plain value object: no `fromInput()`/`toArray()` round-trip through arrays (the
FormRequest builds it directly, the Service reads its typed properties).

**(b) Search/filter normalization object.** A value object that leniently parses and normalizes
GET query parameters (unknown/empty values become `null`, sort keys are constrained to an
allow-list). Its input is an **array**, not a `Request` — the controller extracts the query into
an array first, so Params stays framework-independent. A derived app's
`PublicSketchCatalogFilters` is the exemplar in spirit, adapted to array input:

```php
// app/Params/FooCatalogFilters.php
final readonly class FooCatalogFilters
{
    /** @var list<string> */
    public const array SORTS = ['latest', 'updated', 'popular'];

    public function __construct(
        public ?string $search = null,
        public string $sort = 'latest',
    ) {}

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        // Query values can arrive as arrays (`?q[]=x`); coerce only real strings and let
        // anything else fall back to the default, so a malformed query normalizes instead of 500ing.
        $search = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : '';

        return new self(
            search: $search === '' ? null : $search,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'latest',
        );
    }
}
```

### 4. ViewModels exist in two kinds

- **Page ViewModel** — one per Inertia page, class name ends in `PageViewModel`, implements
  `Inertia\ProvidesInertiaProperties`, and assembles the page's entire props payload (including
  SEO). The controller passes the object straight to `Inertia::render(...)`; the object resolves
  its own props via `toInertiaProperties(RenderContext)`. Three shapes, so ceremony scales with
  complexity, not page count:
  - Pages with page-specific props get a **1:1 concrete class** (e.g. profile, two-factor). This
    is the default whenever the page's props shape is worth fixing as a type — the array-shape
    PHPDoc on `toInertiaProperties` is what Larastan checks against.
  - Pages that only need a title/description get the shared **`StaticPageViewModel`**.
  - Pages whose props are already assembled by fragment ViewModels get the shared
    **`DynamicPageViewModel`** (title/description + a props array + optional noindex). The props
    shape responsibility then lives in the fragment ViewModels' array-shape PHPDocs, not at the
    page level. Absorbed from a derived app, which pioneered the pattern; use it
    instead of inlining raw prop arrays into `Inertia::render(...)` so SEO composition stays
    uniform.
- **Fragment ViewModel** — a formatting part for a piece of a page (a list row, a summary card),
  class name ends in `ViewModel` but not `PageViewModel`. It is a plain value object with a
  `toArray()`; it is not an Inertia page and does not implement `ProvidesInertiaProperties`.
  A derived app's `PartySummaryViewModel` is the exemplar.

The arch rule that enforces `ProvidesInertiaProperties` applies only to `...PageViewModel`
classes, so fragment ViewModels stay exempt.

### 5. Authorization lives in Laravel Policies

Per-model authorization goes in a standard Laravel Policy, not scattered across controllers or
services. A derived app's `SketchPolicy` is the exemplar. The template ships no Policy
of its own — that is acceptable, because Policies are a stock Laravel mechanism and this one-line
rule is enough guidance; a derived app adds Policies as it grows models that need per-instance
authorization.

### 6. Exemplar code stays convention-current

Agents imitate existing code over prose docs, so every reference implementation in the template
is a live showcase of the current conventions. When a convention changes, the exemplar code is
brought into compliance **in the same change** — a stale exemplar teaches the old convention
regardless of what the docs say. The one exception is this ADR's own initial rollout: the
pre-existing exemplars it supersedes are migrated over the follow-up changes named in
Consequences, after which the same-change rule holds without exception.

## Considered alternatives

- **Always introduce Service/Repository/Params for consistency.** Rejected: it adds a boundary
  where there is none to protect, and the empty ceremony (the former `UpdateProfileParams`) is
  exactly the mixed signal this decision removes. Layers earn their place by guarding a real
  boundary.
- **Keep `UpdateProfileParams` as a "Params without a Service" exemplar.** Rejected: it
  demonstrates the anti-pattern rule 3 forbids. The `toParams()` sketch above preserves the
  useful idea (centralize the array-shape cast next to the rules) without shipping a Params that
  no Service consumes.
- **Namespace page vs. fragment ViewModels (`App\ViewModels\Pages\*`).** Rejected: moving the
  existing classes would ripple into every derived app on the next template pull. Distinguishing
  by the `PageViewModel` suffix keeps the arch rule precise without relocating files.
- **Let Params depend on `Request` for lenient query parsing** (as `PublicSketchCatalogFilters`
  does with `fromRequest`). Rejected for the template: it couples the value object to the HTTP
  layer and violates the `App\Params` arch rule. Array input (`fromQuery`) keeps Params
  framework-independent; the controller does the one-line extraction.

## Consequences

- A new use case has a single defensible shape, so agents and derived apps converge instead of
  each inventing a layering. Reviewers check the shape against these rules rather than re-deriving
  it.
- The template's own exemplars must match these rules (rule 6). During this decision's initial
  rollout, the page ViewModels were migrated to `ProvidesInertiaProperties`, and the service-less
  `UpdateProfileParams` / `UpdatePasswordParams` were removed rather than converted to the
  `toParams()` form. Future changes to these conventions must update their template exemplars in
  the same change.
- Rule 3(a) moves the array-shape cast into the FormRequest, so the validation rules and the DTO
  shape are read together; Larastan `max` verifies the cast against the constructor signature.
- The `PageViewModel`-suffix scoping (rule 4) lets derived apps keep fragment ViewModels without
  tripping the page-ViewModel arch rule when the template propagates it
  ([TADR-0003](0003-shared-surface-convergence.md) Tier 1).

# Layer decision rules: when to introduce Service, Repository, Params, and ViewModel

## Status

Accepted.

## Context

The template needs one consistent answer for when a backend layer object is warranted.
Without it, agents and derived apps resolve the same use case differently and the template
teaches drift.

The layer names under decision are:

- **Service** (`app/Services/*`) — a use-case orchestrator behind a `...ServiceContract`,
  normally bound as a singleton.
- **Repository** (`app/Repositories/*`) — persistence mediation behind a
  `...RepositoryContract`.
- **Params** (`app/Params/*`) — a `final readonly` value object, framework-independent
  and kept free of HTTP/session dependencies by architecture tests.
- **ViewModel** (`app/ViewModels/*`) — assembles the data a page or fragment renders.

## Decision

### 1. Allow controller-direct Eloquent for simple CRUD

For a single-model create/update/delete with no transaction, no cross-model coordination, and
no external effect, the controller uses validated input directly against the model. Do **not**
introduce a Service, Repository, or Params object for this shape. Keep array-shape annotations
where Larastan needs them.

### 2. Introduce a Service (with a Repository) for orchestrated use cases

Introduce a Service when a use case involves any of: a database transaction, row locking,
coordination across more than one model, an external API, or sending mail. Persistence for that
use case goes through a Repository contract, and Service / Repository contracts are covered by
the binding-completeness test.

Whether the Service also takes a Params object is decided by rule 3. A Service that consumes a
small external callback payload may take scalar arguments directly.

Fortify Actions are the framework-defined variant of this rule: the Action is the orchestrator.
Do not wrap a Fortify Action in an app Service unless a new boundary appears.

### 3. Params exist in exactly two forms — never a third

A Params object is created only in one of these two shapes. If a use case fits neither, it does
not get a Params object.

**(a) Write DTO at the Controller to Service boundary.** The typed input a Service consumes.
It exists **only when a Service consumes it**. The FormRequest owns conversion to the Params so
validation and the array-shape assertion stay together. The Params exposes typed properties;
avoid array round-trips such as `fromInput()` / `toArray()` unless a real boundary requires them.
Place it under `App\Params\<Domain>\`, name it `...Params`, and keep it `final readonly`.
For write DTOs, FormRequest conversion is the boundary owner; the Params must not parse raw input
or expose `toArray()` just to feed Eloquent.

**(b) Search/filter normalization object.** A value object that leniently parses and normalizes
GET query parameters (unknown/empty values become `null`, sort keys are constrained to an
allow-list). Its input is an array, not a `Request`; the controller extracts the query first so
Params stays framework-independent.
This is the only Params form where a `toArray()`-style method is acceptable, and only for carrying
normalized query parameters back into links or pagination.

### 4. ViewModels exist in two kinds

- **Page ViewModel** — class name ends in `PageViewModel`, implements
  `Inertia\ProvidesInertiaProperties`, and assembles the page's Inertia props including SEO.
  Use a concrete class when the page has page-specific props, `StaticPageViewModel` for
  title/description-only pages, and `DynamicPageViewModel` when fragment ViewModels already own
  the detailed prop shapes.
- **Fragment ViewModel** — formats a piece of a page and returns an array. It is not an Inertia
  page and does not implement `ProvidesInertiaProperties`.

### 5. Authorization lives in Laravel Policies

Per-model authorization goes in a standard Laravel Policy, not scattered across controllers or
services. The template does not need a sample Policy; derived apps add Policies when they grow
models that need per-instance authorization.

### 6. Exemplar code stays convention-current

Agents imitate existing code over prose docs, so every reference implementation in the template
is a live showcase of the current conventions. When a convention changes, the exemplar code must
be brought into compliance in the same change.

## Considered alternatives

- **Always introduce Service/Repository/Params for consistency.** Rejected: it adds a boundary
  where there is none to protect. Layers earn their place by guarding a real boundary.
- **Allow Params without a Service.** Rejected: it creates a layer whose only job is to copy
  already-validated data, without adding a useful boundary.
- **Namespace page vs. fragment ViewModels (`App\ViewModels\Pages\*`).** Rejected: moving the
  existing classes would ripple into every derived app on the next template pull. Distinguishing
  by the `PageViewModel` suffix keeps the arch rule precise without relocating files.
- **Let Params depend on `Request` for lenient query parsing.** Rejected: it couples value objects
  to the HTTP layer and violates the Params architecture rule.

## Consequences

- A new use case has a single defensible shape, so agents and derived apps converge instead of
  each inventing a layering. Reviewers check the shape against these rules rather than re-deriving
  it.
- Architecture tests and static analysis enforce the mechanical parts: dependency direction,
  Params framework-independence, Page ViewModel contracts, and container binding completeness.
- Future changes to these conventions must update the template exemplars and tests in the same
  change.

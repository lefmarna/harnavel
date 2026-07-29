# Backend layer decision rules

Simple single-model CRUD may use validated input and Eloquent directly. Introduce a Service for
transactions, locking, multi-model coordination, mail, or external APIs; persistence for that use
case goes through a Repository contract. Fortify Actions already fill the orchestration role and
are not wrapped without another boundary.

Params are either typed write input at a Controller-to-Service boundary or a
framework-independent search/filter normalizer. They do not parse `Request`, and they expose arrays
only for a real serialization or query-string boundary.

Page ViewModels implement `ProvidesInertiaProperties` and own the page props and SEO contract.
Use `StaticPageViewModel` for copy-only pages and `DynamicPageViewModel` when fragment ViewModels
already own detailed shapes. Fragment ViewModels format only part of a page. Per-model authorization
belongs in Laravel Policies. Mechanical naming and dependency rules stay in architecture tests, and
template exemplars must follow the current conventions because agents learn from them.

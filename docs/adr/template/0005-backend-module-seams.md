# Backend module seams

Simple single-model CRUD stays in a controller with validated Eloquent input; transactions,
locking, multi-model coordination, mail, and external APIs justify a Service, whose persistence
crosses a Repository contract. Fortify Actions already occupy that orchestration seam and are not
wrapped without another responsibility; Params cross a Controller-to-Service seam as typed write
input or encapsulate framework-independent search/filter normalization, never `Request` parsing.
Page ViewModels own page props and SEO, fragment ViewModels format only their fragment, and
per-model authorization stays in Laravel Policies.

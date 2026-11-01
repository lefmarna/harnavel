# Backend module seams

Service and Repository layers require orchestration beyond single-model CRUD; Fortify Actions
already own their flows and are not wrapped without another purpose. Once a model has a repository,
code outside the model reads and writes it only through the repository, so row locks and test fakes
cover every path. Every repository, read-only ones included, is a
`Repositories/<Model>/<Model>RepositoryContract` bound to its implementation, so tests can replace it
and the binding check sees it.

`app/Params` keeps input normalization framework-independent by leaving HTTP parsing at the
delivery boundary. A `*PageViewModel` extends `PageViewModel` to shape one Inertia page's props and
SEO; a fragment several pages share is a plain `*ViewModel` with `toArray()`. A value a service
returns lives in that service's namespace; only values several layers share go to `app/ValueObjects`.

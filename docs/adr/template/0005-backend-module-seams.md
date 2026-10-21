# Backend module seams

Service/Repository seams require orchestration beyond single-model CRUD. Fortify Actions already
provide that responsibility; a wrapper needs another purpose. Once a model has a repository, code
outside the model reads and writes it only through the repository, so row locks and test fakes cover
every path. Every repository, read-only ones included, is a `Repositories/<Model>/<Model>RepositoryContract`
bound to its implementation, so tests can replace it and the binding check sees it.

Input normalization remains framework-independent by keeping HTTP parsing at the delivery boundary
(`app/Params`). A `*PageViewModel` extends `PageViewModel` and shapes one Inertia page's props,
including SEO; a fragment several pages share is a plain `*ViewModel` with `toArray()`. A value a
service returns lives in that service's namespace; only values several layers share go to
`app/ValueObjects`.
